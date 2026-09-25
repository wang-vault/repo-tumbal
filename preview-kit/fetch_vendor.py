#!/usr/bin/env python3
"""Ambil semua paket di composer.lock dari GitHub ke direktori vendor.

Sandbox ini tidak bisa mencapai packagist.org, jadi tiap paket diunduh sebagai
arsip zip git dari codeload.github.com memakai `source.reference` di lock file.
Sekalian mengambil ClassLoader.php + InstalledVersions.php milik Composer.

Pemakaian: python3 fetch_vendor.py <composer.lock> <vendor-dir>
"""
import concurrent.futures
import io
import json
import os
import re
import shutil
import sys
import urllib.request
import zipfile

WORKERS = int(os.environ.get('FETCH_WORKERS', '10'))
REPO_ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))


def http_get(url, timeout=180):
    last = None
    for _ in range(3):
        try:
            req = urllib.request.Request(url, headers={'User-Agent': 'preview-kit'})
            with urllib.request.urlopen(req, timeout=timeout) as r:
                return r.read()
        except Exception as exc:
            last = exc
    raise last


def github_ref(url):
    m = re.match(r'https://github\.com/([^/]+)/([^/]+?)(?:\.git)?/?$', url or '')
    return m.groups() if m else None


def unzip_into(data, dest):
    """Ekstrak zip, buang satu direktori pembungkus teratas."""
    zf = zipfile.ZipFile(io.BytesIO(data))
    names = zf.namelist()
    tops = {n.split('/')[0] for n in names if '/' in n}
    top = tops.pop() if len(tops) == 1 else None
    tmp = dest + '.tmp'
    if os.path.isdir(tmp):
        shutil.rmtree(tmp)
    for info in zf.infolist():
        rel = info.filename[len(top) + 1:] if top and info.filename.startswith(top + '/') else info.filename
        if not rel or rel.endswith('/') or '..' in rel.split('/'):
            continue
        target = os.path.join(tmp, rel)
        os.makedirs(os.path.dirname(target), exist_ok=True)
        with zf.open(info) as src, open(target, 'wb') as out:
            shutil.copyfileobj(src, out)
    if os.path.isdir(dest):
        shutil.rmtree(dest)
    os.makedirs(os.path.dirname(dest), exist_ok=True)
    os.rename(tmp, dest)


def fetch_package(pkg):
    name = pkg['name']
    dest = os.path.join(VENDOR, name)
    if os.path.isdir(dest) and os.listdir(dest):
        return name, 'lewat'
    src = pkg.get('source') or {}
    gh = github_ref(src.get('url'))
    if not gh:
        return name, 'bukan github'
    owner, repo = gh
    ref = src.get('reference') or (pkg.get('version') or '').lstrip('v')
    try:
        unzip_into(http_get(f'https://codeload.github.com/{owner}/{repo}/zip/{ref}'), dest)
    except Exception as exc:
        return name, f'gagal ({exc})'
    return name, 'ok'


def fetch_composer_runtime():
    """ClassLoader + InstalledVersions dari rilis composer terbaru."""
    dest = os.path.join(VENDOR, 'composer')
    os.makedirs(dest, exist_ok=True)
    need = ['ClassLoader.php', 'InstalledVersions.php']
    if all(os.path.isfile(os.path.join(dest, n)) for n in need):
        return 'lewat'
    meta = json.loads(http_get('https://api.github.com/repos/composer/composer/releases/latest'))
    tag = meta.get('tag_name') or '2.8.0'
    zf = zipfile.ZipFile(io.BytesIO(http_get(f'https://codeload.github.com/composer/composer/zip/{tag}')))
    got = 0
    for info in zf.infolist():
        base = info.filename.rsplit('/', 1)[-1]
        if base in need and '/src/Composer/' in info.filename:
            with zf.open(info) as src, open(os.path.join(dest, base), 'wb') as out:
                shutil.copyfileobj(src, out)
            got += 1
    return f'ok ({tag}, {got} berkas)'


if __name__ == '__main__':
    lock_path = sys.argv[1] if len(sys.argv) > 1 else os.path.join(REPO_ROOT, 'composer.lock')
    VENDOR = os.path.abspath(sys.argv[2] if len(sys.argv) > 2 else os.path.join(REPO_ROOT, 'vendor'))
    lock = json.load(open(lock_path, encoding='utf-8'))
    pkgs = lock['packages'] + lock['packages-dev']
    os.makedirs(VENDOR, exist_ok=True)

    print(f'  mengunduh {len(pkgs)} paket ke {VENDOR}')
    hasil = {}
    with concurrent.futures.ThreadPoolExecutor(max_workers=WORKERS) as ex:
        for name, status in ex.map(fetch_package, pkgs):
            hasil[name] = status
            if status.startswith('gagal') or status == 'bukan github':
                print(f'    ! {name}: {status}')
    ok = sum(1 for s in hasil.values() if s in ('ok', 'lewat'))
    print(f'  paket siap: {ok}/{len(pkgs)}')
    print(f'  runtime composer: {fetch_composer_runtime()}')
    sys.exit(0 if ok == len(pkgs) else 1)
