#!/usr/bin/env python3
"""Rakit autoloader gaya Composer tanpa menjalankan `composer dump-autoload`.

Menghasilkan vendor/autoload.php + vendor/composer/{autoload_psr4,
autoload_namespaces,autoload_classmap,autoload_files,installed}.php(.json).

Pemakaian: python3 gen_autoload.py <composer.json> <composer.lock> <vendor-dir>
"""
import hashlib
import json
import os
import re
import sys

CLASS_RE = re.compile(
    r'^\s*(?:final\s+|abstract\s+|readonly\s+)*(?:class|interface|trait|enum)\s+'
    r'([A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*)',
    re.M,
)
NS_RE = re.compile(r'^\s*namespace\s+([A-Za-z0-9_\\]+)\s*[;{]', re.M)


def php_str(s):
    return "'" + s.replace('\\', '\\\\').replace("'", "\\'") + "'"


def php_array(entries):
    """Tulis larik PHP dengan $vendorDir/$baseDir, meniru hasil Composer."""
    lines = ['<?php', '', '$vendorDir = dirname(__DIR__);', '$baseDir = dirname($vendorDir);', '', 'return array(']
    for key, values in entries:
        single = isinstance(values, str)
        values = [values] if single else list(values)
        rendered = []
        for v in values:
            if v.startswith('$vendorDir/'):
                rendered.append("$vendorDir . " + php_str(v[len('$vendorDir'):]))
            elif v.startswith('$baseDir/'):
                rendered.append("$baseDir . " + php_str(v[len('$baseDir'):]))
            else:
                rendered.append(php_str(v))
        if single:
            lines.append(f'    {php_str(key)} => {rendered[0]},')
        else:
            lines.append(f'    {php_str(key)} => array({", ".join(rendered)}),')
    lines += [');', '']
    return '\n'.join(lines)


def rel_to_vendor(path, vendor):
    p, v = os.path.abspath(path), os.path.abspath(vendor)
    if p == v or p.startswith(v + os.sep):
        return '$vendorDir/' + os.path.relpath(p, v).replace(os.sep, '/')
    return '$baseDir/' + os.path.relpath(p, os.path.dirname(v)).replace(os.sep, '/')


def scan_classmap(target, excludes, classmap):
    if os.path.isfile(target):
        files = [target]
    elif os.path.isdir(target):
        files = []
        for dirpath, dirnames, filenames in os.walk(target):
            dirnames[:] = [d for d in dirnames if d not in ('.git', 'Tests', 'tests')]
            files += [os.path.join(dirpath, f) for f in filenames if f.endswith('.php')]
    else:
        return
    for f in files:
        if any(re.search(ex.strip('{}'), f.replace(os.sep, '/')) for ex in excludes if ex):
            continue
        try:
            src = open(f, encoding='utf-8', errors='ignore').read()
        except OSError:
            continue
        ns_match = NS_RE.search(src)
        ns = ns_match.group(1) if ns_match else ''
        for m in CLASS_RE.finditer(src):
            classmap.setdefault(f'{ns}\\{m.group(1)}' if ns else m.group(1), f)


def normalize_version(raw):
    v = (raw or '').lstrip('v')
    m = re.match(r'^(\d+)(?:\.(\d+))?(?:\.(\d+))?(?:\.(\d+))?', v)
    if not m:
        return v or '0.0.0.0'
    return '.'.join([m.group(1), m.group(2) or '0', m.group(3) or '0', m.group(4) or '0'])


def main():
    composer_json, composer_lock, vendor = sys.argv[1], sys.argv[2], sys.argv[3]
    root = json.load(open(composer_json, encoding='utf-8'))
    lock = json.load(open(composer_lock, encoding='utf-8'))
    packages = lock['packages'] + lock['packages-dev']
    dev_names = {p['name'] for p in lock['packages-dev']}

    psr4, psr0, classmap, files, entries = {}, {}, {}, [], []

    # autoload-dev paket akar (Tests\) digabung supaya ikut terdaftar
    root = dict(root)
    root_auto = dict(root.get('autoload') or {})
    for key, value in (root.get('autoload-dev') or {}).items():
        if key in ('psr-4', 'psr-0') and isinstance(value, dict):
            merged = dict(root_auto.get(key) or {})
            merged.update(value)
            root_auto[key] = merged
        elif key in ('files', 'classmap'):
            root_auto[key] = (root_auto.get(key) or []) + value
    root['autoload'] = root_auto

    all_defs = [(root, os.path.dirname(os.path.abspath(composer_json)), True)]
    all_defs += [(p, os.path.join(vendor, p['name']), False) for p in packages]

    root_prefixes = set((root_auto.get('psr-4') or {})) | set((root_auto.get('psr-0') or {}))
    skipped = []

    for pkg, pdir, is_root in all_defs:
        auto = pkg.get('autoload') or {}
        if not is_root:
            # Paket dev seperti laravel/pint ikut mendeklarasikan App\ dst.
            # Binari mereka jalan sendiri, jadi autoloader-nya dilewati.
            declared = set((auto.get('psr-4') or {})) | set((auto.get('psr-0') or {}))
            if declared & root_prefixes:
                skipped.append(pkg.get('name'))
                auto = {}
        excludes = auto.get('exclude-from-classmap') or []
        for prefix, paths in (auto.get('psr-4') or {}).items():
            paths = [paths] if isinstance(paths, str) else list(paths)
            psr4.setdefault(prefix, []).extend(rel_to_vendor(os.path.join(pdir, p), vendor) for p in paths)
        for prefix, paths in (auto.get('psr-0') or {}).items():
            paths = [paths] if isinstance(paths, str) else list(paths)
            psr0.setdefault(prefix, []).extend(rel_to_vendor(os.path.join(pdir, p), vendor) for p in paths)
        for path in (auto.get('classmap') or []):
            scan_classmap(os.path.join(pdir, path), excludes, classmap)
        for path in (auto.get('files') or []):
            full = os.path.join(pdir, path)
            if os.path.isfile(full):
                files.append(rel_to_vendor(full, vendor))

        name = pkg.get('name') or '__root__'
        pretty = pkg.get('version') if not is_root else (root.get('version') or 'dev-main')
        entries.append((name, {
            'name': name,
            'pretty_version': pretty,
            'version': pretty if str(pretty).startswith('dev-') else normalize_version(pretty),
            'reference': (pkg.get('source') or {}).get('reference'),
            'type': pkg.get('type') or ('project' if is_root else 'library'),
            'install_path': pdir,
            'aliases': [],
            'dev_requirement': name in dev_names,
            'description': pkg.get('description') or '',
            'license': pkg.get('license') or [],
            'authors': pkg.get('authors') or [],
            'require': pkg.get('require') or {},
            'autoload': auto,
        }))

    comp_dir = os.path.join(vendor, 'composer')
    os.makedirs(comp_dir, exist_ok=True)
    open(os.path.join(comp_dir, 'autoload_psr4.php'), 'w', encoding='utf-8').write(php_array(sorted(psr4.items())))
    open(os.path.join(comp_dir, 'autoload_namespaces.php'), 'w', encoding='utf-8').write(php_array(sorted(psr0.items())))
    open(os.path.join(comp_dir, 'autoload_classmap.php'), 'w', encoding='utf-8').write(
        php_array(sorted((k, rel_to_vendor(v, vendor)) for k, v in classmap.items())))
    files_map = {hashlib.md5(f.encode()).hexdigest(): f for f in files}
    open(os.path.join(comp_dir, 'autoload_files.php'), 'w', encoding='utf-8').write(php_array(sorted(files_map.items())))

    root_entry = next((v for _, v in entries if v['type'] == 'project'), entries[0][1])
    lines = ['<?php', '', 'return array(', "    'root' => array("]
    for key in ('name', 'pretty_version', 'version', 'reference', 'type', 'install_path'):
        val = root_entry.get(key)
        lines.append(f"        '{key}' => " + ('null' if val is None else php_str(str(val))) + ',')
    lines += ["        'aliases' => array(),", "        'dev_requirement' => false,", '    ),', "    'versions' => array("]
    for _, pkg in entries:
        lines.append(f"        {php_str(pkg['name'])} => array(")
        for key in ('pretty_version', 'version', 'reference', 'type', 'install_path'):
            val = pkg.get(key)
            lines.append(f"            '{key}' => " + ('null' if val is None else php_str(str(val))) + ',')
        lines.append("            'aliases' => array(),")
        lines.append(f"            'dev_requirement' => {str(bool(pkg['dev_requirement'])).lower()},")
        lines.append('        ),')
    lines += ['    ),', ');', '']
    open(os.path.join(comp_dir, 'installed.php'), 'w', encoding='utf-8').write('\n'.join(lines))
    open(os.path.join(comp_dir, 'installed.json'), 'w', encoding='utf-8').write(json.dumps({
        'packages': [v for _, v in entries], 'dev': True, 'dev-package-names': sorted(dev_names)}, indent=2))

    open(os.path.join(vendor, 'autoload.php'), 'w', encoding='utf-8').write("""<?php

// Autoloader rakitan untuk sandbox preview (bukan hasil `composer dump-autoload`).
// Dibangkitkan oleh preview-kit/gen_autoload.py — jangan disunting tangan.

// Boleh dimuat lebih dari sekali: PHPUnit memuat vendor/autoload.php lalu
// bootstrap test memuatnya lagi.
if (isset($GLOBALS['__preview_autoload_loader'])) {
    return $GLOBALS['__preview_autoload_loader'];
}

require_once __DIR__ . '/composer/ClassLoader.php';
require_once __DIR__ . '/composer/InstalledVersions.php';

$loader = new \\Composer\\Autoload\\ClassLoader(__DIR__);

foreach (require __DIR__ . '/composer/autoload_namespaces.php' as $namespace => $path) {
    $loader->set($namespace, $path);
}

foreach (require __DIR__ . '/composer/autoload_psr4.php' as $namespace => $path) {
    $loader->setPsr4($namespace, $path);
}

$classMap = require __DIR__ . '/composer/autoload_classmap.php';
if ($classMap) {
    $loader->addClassMap($classMap);
}

$loader->register(true);

$includeFiles = require __DIR__ . '/composer/autoload_files.php';
foreach ($includeFiles as $fileIdentifier => $file) {
    if (empty($GLOBALS['__composer_autoload_files'][$fileIdentifier])) {
        require $file;
        $GLOBALS['__composer_autoload_files'][$fileIdentifier] = true;
    }
}

// InstalledVersions memuat composer/installed.php sendiri saat dibutuhkan.

$GLOBALS['__preview_autoload_loader'] = $loader;

return $loader;
""")

    print(f'  psr-4 {len(psr4)} prefiks | psr-0 {len(psr0)} | classmap {len(classmap)} | '
          f'files {len(files_map)} | paket {len(entries)}')
    if skipped:
        print(f'  autoload dilewati (prefiks bentrok dengan paket akar): {", ".join(skipped)}')


if __name__ == '__main__':
    main()
