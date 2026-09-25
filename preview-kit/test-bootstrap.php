<?php

/**
 * Bootstrap PHPUnit untuk sandbox PHP-WASM.
 *
 * Menjalankan migrasi di dalam proses PHPUnit (lewat RefreshDatabase) membuat
 * WASM kehabisan memori, jadi migrasinya dikerjakan sekali di sini — di luar
 * suite — lalu PHPUnit diberi tahu bahwa migrasi sudah beres. Tiap test tinggal
 * membungkus dirinya dalam transaksi. Karena satu proses hanya kuat menjalankan
 * satu test, pakai preview-kit/run-tests.sh.
 *
 * Bukan bagian aplikasi — hanya perkakas sandbox.
 */

$root = getenv('APP_ROOT') ?: dirname(__DIR__);

require $root.'/vendor/autoload.php';

$dbFile = getenv('TEST_DB') ?: sys_get_temp_dir().'/preview-test.sqlite';
touch($dbFile);

// phpunit.xml menyetel DB_DATABASE=:memory: lebih dulu; di sini ditimpa supaya
// semua test dalam satu proses memakai berkas yang sama.
foreach ([
    'APP_ENV' => 'testing',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => $dbFile,
    'DB_URL' => '',
    'CACHE_STORE' => 'array',
    'SESSION_DRIVER' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'MAIL_MAILER' => 'array',
    'BCRYPT_ROUNDS' => '4',
] as $key => $value) {
    putenv("$key=$value");
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

$app = require_once $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

Illuminate\Support\Facades\Artisan::call('migrate:fresh', ['--force' => true]);

Illuminate\Foundation\Testing\RefreshDatabaseState::$migrated = true;
