#!/usr/bin/env bash
#
# Bangun ulang sandbox preview dari nol.
#
# Sandbox ini tidak punya PHP/Composer asli dan /tmp bisa ter-wipe kapan saja,
# jadi perkakasnya disimpan di preview-kit/ (ikut di-commit) dan skrip ini
# merakit ulang semuanya: PHP-WASM lewat npm, 111 paket vendor diunduh dari
# GitHub (packagist tidak terjangkau), autoloader dirakit sendiri, lalu database
# SQLite dimigrasi + diisi seeder.
#
#   bash preview-kit/setup.sh            # salin repo, vendor, migrasi + seed
#   bash preview-kit/setup.sh --demo     # sama + tambah data contoh
#   bash preview-kit/setup.sh --tests    # sama + jalankan seluruh suite test
#
# Sesudah itu nyalakan servernya:
#   APP_ROOT=/tmp/verify/app PORT=8080 node preview-kit/server.mjs
#
set -uo pipefail

REPO="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
KIT="$REPO/preview-kit"
WORK="${WORK:-/tmp/verify}"
APP="$WORK/app"
PHPLINT="${PHPLINT:-/tmp/phplint}"
PHP="$PHPLINT/node_modules/.bin/php-wasm-cli"
MEM="${PHP_MEMORY:-768M}"
DEMO=""
TESTS=""
for arg in "$@"; do
    case "$arg" in
        --demo) DEMO=1 ;;
        --tests) TESTS=1 ;;
    esac
done

step() { printf '\n== %s\n' "$1"; }
artisan() { (cd "$APP" && "$PHP" -d memory_limit="$MEM" artisan "$@"); }

step "1/8 PHP-WASM lewat npm"
if [ ! -x "$PHP" ]; then
    mkdir -p "$PHPLINT"
    cd "$PHPLINT"
    [ -f package.json ] || npm init -y >/dev/null 2>&1
    npm i @php-wasm/cli@3.1.55 --silent >/dev/null 2>&1
fi
if [ ! -x "$PHP" ]; then
    echo "  !! php-wasm-cli tidak terpasang (npm gagal?)"; exit 1
fi
"$PHP" -r 'echo "  PHP ".PHP_VERSION."\n";'

step "2/8 salin repo ke $APP"
mkdir -p "$APP"
(cd "$REPO" && tar cf - --exclude=.git --exclude=node_modules --exclude=vendor .) | (cd "$APP" && tar xf -)
printf '  %s berkas disalin\n' "$(find "$APP" -type f | wc -l | tr -d ' ')"

step "3/8 periksa jembatan CGI"
"$PHP" -l "$APP/preview-kit/bridge.php" | sed 's/^/  /'

step "4/8 unduh vendor dari GitHub (packagist tertutup)"
python3 "$KIT/fetch_vendor.py" "$APP/composer.lock" "$APP/vendor" || {
    echo "  !! ada paket yang gagal diunduh"; exit 1; }

step "5/8 rakit autoloader"
python3 "$KIT/gen_autoload.py" "$APP/composer.json" "$APP/composer.lock" "$APP/vendor" || {
    echo "  !! autoloader gagal dirakit"; exit 1; }

step "6/8 .env + database SQLite"
[ -f "$APP/.env" ] || cp "$APP/.env.example" "$APP/.env"
touch "$APP/database/database.sqlite"
artisan key:generate --force 2>&1 | tail -1 | sed 's/^/  /'

step "7/8 migrasi + seed + cache tampilan"
artisan migrate:fresh --seed --force 2>&1 | tail -4 | sed 's/^/  /'
artisan view:cache 2>&1 | tail -1 | sed 's/^/  /'

step "8/8 ringkasan"
(cd "$APP" && APP_ROOT="$APP" "$PHP" -d memory_limit="$MEM" -r '
$root = getenv("APP_ROOT");
require $root."/vendor/autoload.php";
$app = require_once $root."/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
printf("  produk %d | pesanan %d | pengguna %d | migrasi %d berkas\n",
    App\Models\Product::count(), App\Models\Order::count(), App\Models\User::count(),
    count(glob($root."/database/migrations/*.php")));
' 2>&1 | tail -2)

if [ -n "$DEMO" ]; then
    printf '\n== tambahan: data contoh (--demo)\n'
    (cd "$APP" && APP_ROOT="$APP" "$PHP" -d memory_limit="$MEM" preview-kit/addproducts.php | sed 's/^/  /')
fi

if [ -n "$TESTS" ]; then
    printf '\n== tambahan: seluruh suite test (--tests)\n'
    APP_ROOT="$APP" bash "$KIT/run-tests.sh" || true
fi

printf '\nSiap. Nyalakan server preview dengan:\n'
printf '  APP_ROOT=%s PORT=8080 node %s/server.mjs\n\n' "$APP" "$KIT"
