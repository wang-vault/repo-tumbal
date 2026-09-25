<?php

/**
 * Jembatan CGI untuk sandbox preview.
 *
 * server.mjs menjalankan berkas ini lewat PHP-WASM untuk tiap request: metode,
 * URI, header, dan body masuk lewat variabel lingkungan + stdin, lalu balasan
 * Laravel ditulis ke stdout dalam format CGI (Status: / header / baris kosong /
 * body). Bukan bagian aplikasi — hanya perkakas sandbox.
 */

$root = getenv('APP_ROOT') ?: getcwd();

$method = getenv('REQUEST_METHOD') ?: 'GET';
$uri = getenv('REQUEST_URI') ?: '/';
$query = getenv('QUERY_STRING') ?: '';
$body = stream_get_contents(STDIN);
if ($body === false) {
    $body = '';
}

$env = getenv() ?: [];

parse_str($query, $_GET);

$server = [
    'REQUEST_METHOD' => $method,
    'REQUEST_URI' => $uri,
    'QUERY_STRING' => $query,
    'SCRIPT_NAME' => '/index.php',
    'SCRIPT_FILENAME' => $root.'/public/index.php',
    'PHP_SELF' => '/index.php',
    'DOCUMENT_ROOT' => $root.'/public',
    'SERVER_PROTOCOL' => 'HTTP/1.1',
    'SERVER_SOFTWARE' => 'php-wasm-preview',
    'SERVER_NAME' => getenv('HTTP_HOST') ? preg_replace('/:\d+$/', '', getenv('HTTP_HOST')) : 'localhost',
    'SERVER_PORT' => getenv('PORT') ?: '8080',
    'REMOTE_ADDR' => '127.0.0.1',
    'HTTPS' => '',
];
foreach ($env as $key => $value) {
    if (str_starts_with($key, 'HTTP_') || in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
        $server[$key] = $value;
    }
}
$_SERVER = $server;

// Cookie dipisah manual karena PHP CLI tidak mengisinya dari env.
$_COOKIE = [];
foreach (explode(';', getenv('HTTP_COOKIE') ?: '') as $part) {
    if (! str_contains($part, '=')) {
        continue;
    }
    [$name, $value] = explode('=', $part, 2);
    $_COOKIE[trim($name)] = urldecode(trim($value));
}

// Badan request diurai sendiri supaya tidak bergantung cara Symfony membaca
// php://input di SAPI CLI.
$params = [];
$contentType = getenv('CONTENT_TYPE') ?: '';
if (! in_array($method, ['GET', 'HEAD'], true) && $body !== '') {
    if (str_contains($contentType, 'application/x-www-form-urlencoded')) {
        parse_str($body, $params);
    } elseif (str_contains($contentType, 'application/json')) {
        $decoded = json_decode($body, true);
        $params = is_array($decoded) ? $decoded : [];
    }
}
$_POST = $params;

require $root.'/vendor/autoload.php';

$app = require_once $root.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$request = Illuminate\Http\Request::create($uri, $method, $params, $_COOKIE, [], $server, $body);

$response = $kernel->handle($request);

$out = fopen('php://stdout', 'w');
fwrite($out, 'Status: '.$response->getStatusCode()."\r\n");
foreach ($response->headers->allPreserveCaseWithoutCookies() as $name => $values) {
    foreach ((array) $values as $value) {
        fwrite($out, $name.': '.$value."\r\n");
    }
}
foreach ($response->headers->getCookies() as $cookie) {
    fwrite($out, 'Set-Cookie: '.(string) $cookie."\r\n");
}
fwrite($out, "\r\n");
fwrite($out, (string) $response->getContent());
fclose($out);

$kernel->terminate($request, $response);
