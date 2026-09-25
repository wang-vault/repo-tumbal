/**
 * Server preview untuk sandbox: HTTP Node -> PHP-WASM (CGI) -> Laravel.
 *
 * `php artisan serve` tidak bisa dipakai di sini karena PHP-nya berjalan sebagai
 * WebAssembly lewat @php-wasm/cli. Jadi server ini melayani berkas statis dari
 * public/ sendiri dan meneruskan request lain ke preview-kit/bridge.php.
 *
 * Bukan bagian aplikasi — hanya perkakas sandbox.
 *
 *   APP_ROOT=/tmp/verify/app PORT=8080 node preview-kit/server.mjs
 */
import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
import { spawn } from 'node:child_process';

const APP = process.env.APP_ROOT || '/tmp/verify/app';
const PUB = path.join(APP, 'public');
const PHP = process.env.PHP_BIN || '/tmp/phplint/node_modules/.bin/php-wasm-cli';
const BRIDGE = process.env.BRIDGE || path.join(APP, 'preview-kit', 'bridge.php');
const PORT = Number(process.env.PORT || 8080);
const MEM = process.env.PHP_MEMORY || '768M';
const ERROR_LOG = process.env.PHP_ERROR_LOG || '/tmp/verify/php-error.log';
const TIMEOUT = Number(process.env.PHP_TIMEOUT_MS || 180000);

const MIME = {
  '.css': 'text/css; charset=utf-8',
  '.js': 'text/javascript; charset=utf-8',
  '.mjs': 'text/javascript; charset=utf-8',
  '.json': 'application/json',
  '.svg': 'image/svg+xml',
  '.png': 'image/png',
  '.jpg': 'image/jpeg',
  '.jpeg': 'image/jpeg',
  '.gif': 'image/gif',
  '.webp': 'image/webp',
  '.ico': 'image/x-icon',
  '.woff': 'font/woff',
  '.woff2': 'font/woff2',
  '.ttf': 'font/ttf',
  '.txt': 'text/plain; charset=utf-8',
  '.map': 'application/json',
  '.webmanifest': 'application/manifest+json',
  '.html': 'text/html; charset=utf-8',
};

const log = (...args) => console.log(new Date().toISOString().slice(11, 19), ...args);

function readBody(req) {
  return new Promise((resolve) => {
    const chunks = [];
    req.on('data', (c) => chunks.push(c));
    req.on('end', () => resolve(Buffer.concat(chunks)));
  });
}

function serveStatic(res, file) {
  const ext = path.extname(file).toLowerCase();
  res.writeHead(200, {
    'Content-Type': MIME[ext] || 'application/octet-stream',
    'Content-Length': fs.statSync(file).size,
    'Cache-Control': 'no-cache',
  });
  fs.createReadStream(file).pipe(res);
}

function runPhp(req, res, url, body) {
  const env = {
    ...process.env,
    APP_ROOT: APP,
    PORT: String(PORT),
    REQUEST_METHOD: req.method,
    REQUEST_URI: url.pathname + url.search,
    QUERY_STRING: url.search.replace(/^\?/, ''),
    CONTENT_TYPE: req.headers['content-type'] || '',
    CONTENT_LENGTH: String(body.length),
    HTTP_HOST: req.headers.host || `localhost:${PORT}`,
  };
  if (req.headers.cookie) env.HTTP_COOKIE = req.headers.cookie;
  for (const [key, value] of Object.entries(req.headers)) {
    if (['host', 'cookie', 'content-type', 'content-length'].includes(key)) continue;
    env['HTTP_' + key.toUpperCase().replace(/-/g, '_')] = Array.isArray(value) ? value.join(',') : String(value);
  }

  const args = ['-d', `memory_limit=${MEM}`, '-d', 'display_errors=0', '-d', 'log_errors=1',
    '-d', `error_log=${ERROR_LOG}`, BRIDGE];
  const child = spawn(PHP, args, { cwd: APP, env });

  const out = [];
  const err = [];
  let settled = false;
  const timer = setTimeout(() => {
    if (settled) return;
    settled = true;
    child.kill('SIGKILL');
    log('  ! waktu habis', req.method, url.pathname);
    if (!res.headersSent) res.writeHead(504, { 'Content-Type': 'text/plain; charset=utf-8' });
    res.end('504 PHP-WASM timeout\n');
  }, TIMEOUT);

  child.stdout.on('data', (c) => out.push(c));
  child.stderr.on('data', (c) => err.push(c));
  child.on('close', (code) => {
    if (settled) return;
    settled = true;
    clearTimeout(timer);
    const raw = Buffer.concat(out);
    if (!raw.length) {
      let tail = '';
      try {
        tail = fs.readFileSync(ERROR_LOG, 'utf8').split('\n').filter(Boolean).slice(-6).join('\n');
      } catch { /* belum ada log */ }
      log('  ! PHP tidak menghasilkan keluaran (kode', code + ')', req.method, url.pathname);
      if (err.length) log('    stderr:', Buffer.concat(err).toString().slice(0, 400).replace(/\n/g, ' | '));
      if (tail) log('    error_log:', tail.slice(0, 600).replace(/\n/g, ' | '));
      if (!res.headersSent) res.writeHead(500, { 'Content-Type': 'text/plain; charset=utf-8' });
      return res.end(`500 PHP-WASM gagal (kode ${code})\n${tail}\n`);
    }

    const sep = raw.indexOf('\r\n\r\n');
    const headBuf = sep === -1 ? raw : raw.subarray(0, sep);
    const bodyBuf = sep === -1 ? Buffer.alloc(0) : raw.subarray(sep + 4);
    let status = 200;
    const headers = [];
    for (const line of headBuf.toString('utf8').split('\r\n')) {
      if (!line) continue;
      const idx = line.indexOf(':');
      const name = line.slice(0, idx).trim();
      const value = line.slice(idx + 1).trim();
      if (name.toLowerCase() === 'status') { status = Number(value) || 200; continue; }
      if (name.toLowerCase() === 'transfer-encoding') continue;
      headers.push([name, value]);
    }
    res.writeHead(status, [...headers, ['Content-Length', String(bodyBuf.length)]]);
    res.end(bodyBuf);
  });
  child.on('error', (e) => {
    if (settled) return;
    settled = true;
    clearTimeout(timer);
    log('  ! gagal menjalankan PHP:', e.message);
    if (!res.headersSent) res.writeHead(500, { 'Content-Type': 'text/plain; charset=utf-8' });
    res.end(`500 tidak bisa menjalankan ${PHP}: ${e.message}\n`);
  });

  child.stdin.end(body);
}

http.createServer(async (req, res) => {
  const url = new URL(req.url, `http://${req.headers.host || 'localhost'}`);

  // Berkas statis di public/ (CSS, gambar, dsb.) — index.php tetap lewat PHP.
  if (url.pathname !== '/' && !url.pathname.endsWith('.php')) {
    const file = path.normalize(path.join(PUB, url.pathname));
    if (file.startsWith(PUB) && fs.existsSync(file) && fs.statSync(file).isFile()) {
      log(req.method, url.pathname, '→ statis');
      return serveStatic(res, file);
    }
  }

  const body = await readBody(req);
  const started = Date.now();
  const origEnd = res.end.bind(res);
  res.end = (...args) => {
    log(req.method, url.pathname, '→', res.statusCode, `${Date.now() - started}ms`);
    return origEnd(...args);
  };
  runPhp(req, res, url, body);
}).listen(PORT, '0.0.0.0', () => {
  log(`Preview Laravel (PHP-WASM) mendengarkan di 0.0.0.0:${PORT}`);
  log(`  app   : ${APP}`);
  log(`  bridge: ${BRIDGE}`);
  log(`  php   : ${PHP}`);
});
