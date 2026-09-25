<?php

/**
 * Tambah data contoh untuk preview (dipakai `setup.sh --demo`).
 *
 * Seeder bawaan sudah membuat 10 produk + 6 pesanan; skrip ini menambah
 * beberapa lagi supaya katalog punya lebih dari satu halaman dan halaman
 * testimoni punya lebih dari satu kartu. Bukan bagian aplikasi.
 */

$root = getenv('APP_ROOT') ?: dirname(__DIR__);

require $root.'/vendor/autoload.php';

$app = require_once $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Order;
use App\Models\Product;

Product::factory()->count(11)->create();
Order::factory()->count(2)->create();
Order::factory()->claimed()->create();
Order::factory()->paid()->create();
Order::factory()->processing()->create();
Order::factory()->done()->count(2)->create();

printf("  produk: %d | pesanan: %d (DONE: %d, lunas: %d)\n",
    Product::count(), Order::count(), Order::done()->count(),
    Order::where('payment_status', 'PAID')->count());
