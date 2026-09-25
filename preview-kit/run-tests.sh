#!/usr/bin/env bash
#
# Jalankan seluruh suite test di atas PHP-WASM: satu proses per test, karena
# RefreshDatabase + banyak test dalam satu proses membuat WASM kehabisan memori.
# Di mesin biasa cukup `php artisan test`.
#
#   bash preview-kit/run-tests.sh
#
set -uo pipefail

APP="${APP_ROOT:-/tmp/verify/app}"
PHP="${PHP_BIN:-/tmp/phplint/node_modules/.bin/php-wasm-cli}"
MEM="${PHP_MEMORY:-768M}"

pass=0
fail=0
failed=""

for f in $(cd "$APP" && ls tests/Feature/*.php tests/Unit/*.php 2>/dev/null); do
    for t in $(grep -oE 'public function (test_[a-zA-Z0-9_]+)' "$APP/$f" | awk '{print $3}'); do
        out=$(cd "$APP" && APP_ROOT="$APP" "$PHP" -d memory_limit="$MEM" \
            vendor/phpunit/phpunit/phpunit --bootstrap preview-kit/test-bootstrap.php \
            --no-coverage --filter "/::$t\$/" 2>&1 | tail -4)
        if echo "$out" | grep -qE '^OK'; then
            pass=$((pass + 1))
        else
            fail=$((fail + 1))
            failed="$failed $(basename "$f")::$t"
            echo "  x $(basename "$f")::$t"
        fi
    done
    printf '  %-34s selesai\n' "$(basename "$f")"
done

echo
echo "LULUS: $pass   GAGAL: $fail"
[ -n "$failed" ] && echo "gagal:$failed"
[ "$fail" -eq 0 ]
