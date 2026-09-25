<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\TestimonialController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Halaman publik
|--------------------------------------------------------------------------
| Etalase toko: katalog, detail produk, dan testimoni boleh dibaca siapa saja.
*/

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/about', [HomeController::class, 'about'])->name('about');
Route::get('/downloader', [HomeController::class, 'downloader'])->name('downloader');
Route::get('/testimoni', [TestimonialController::class, 'index'])->name('testimoni');
Route::get('/products', [ProductController::class, 'index'])->name('product-list');

/*
|--------------------------------------------------------------------------
| Memesan — pembeli tidak perlu masuk
|--------------------------------------------------------------------------
| Kode pesanan (ORD-YYYYMMDD-XXXXXX) yang menjadi kunci halaman pemesannya.
| Aksi menulis dibatasi throttle karena rute ini terbuka untuk siapa saja.
*/

Route::get('/checkout/{product}', [CheckoutController::class, 'create'])->name('checkout');

Route::middleware('throttle:order-create')->group(function () {
    Route::post('/checkout/{product}', [CheckoutController::class, 'store'])->name('order-store');
});

Route::middleware('throttle:order-claim')->group(function () {
    Route::post('/orders/{order:order_code}/claim', [OrderController::class, 'claim'])->name('order-claim');
});

/*
|--------------------------------------------------------------------------
| Halaman pesanan (publik) + kelola pesanan (penjual)
|--------------------------------------------------------------------------
| {order:order_code} mengikat model lewat kode pesanan, bukan id, jadi alamatnya
| tidak bisa ditebak berurutan. `/orders` (daftar kelola) tidak bentrok dengan
| `/orders/{order}` maupun `/orders/{order}/edit` karena jumlah segmennya beda.
*/

Route::get('/orders/{order:order_code}', [OrderController::class, 'show'])->name('order-show');

Route::middleware('auth')->group(function () {
    Route::get('/orders', [OrderController::class, 'index'])->name('order-list');
    Route::get('/orders/{order:order_code}/edit', [OrderController::class, 'edit'])->name('order-edit');

    // Semua aksi tulis penjual di sini memakai satu limiter yang sama.
    Route::middleware('throttle:order-status')->group(function () {
        Route::post('/orders/{order:order_code}/status', [OrderController::class, 'updateStatus'])->name('order-status');
        Route::post('/orders/{order:order_code}/reject-claim', [OrderController::class, 'rejectClaim'])->name('order-claim-reject');
        Route::put('/orders/{order:order_code}', [OrderController::class, 'update'])->name('order-update');
        Route::delete('/orders/{order:order_code}', [OrderController::class, 'destroy'])->name('order-destroy');
    });
});

/*
|--------------------------------------------------------------------------
| Khusus penjual yang sudah masuk
|--------------------------------------------------------------------------
| PENTING: kelompok ini harus didaftarkan SEBELUM `/products/{product}`,
| kalau tidak "create" dan "edit" akan ditangkap sebagai parameter {product}.
*/

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    // Membuka form tidak dibatasi; yang dibatasi hanya aksi menulisnya.
    Route::get('/products/create', [ProductController::class, 'create'])->name('product-create');
    Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->name('product-edit');

    // throttle:product-write = 20 tulisan/menit per penjual, didefinisikan di
    // AppServiceProvider::configureRateLimiting(). Pelanggaran menghasilkan 429
    // yang dirender resources/views/errors/429.blade.php.
    Route::middleware('throttle:product-write')->group(function () {
        Route::post('/products', [ProductController::class, 'store'])->name('product-store');
        Route::put('/products/{product}', [ProductController::class, 'update'])->name('product-update');
        Route::delete('/products/{product}', [ProductController::class, 'destroy'])->name('product-destroy');
    });
});

/*
|--------------------------------------------------------------------------
| Detail produk (publik)
|--------------------------------------------------------------------------
*/

Route::get('/products/{product}', [ProductController::class, 'show'])->name('product-show');

/*
|--------------------------------------------------------------------------
| Login — hanya untuk tamu
|--------------------------------------------------------------------------
| Middleware `guest` memantulkan penjual yang sudah masuk kembali ke beranda
| (lihat redirectGuestsTo di bootstrap/app.php).
*/

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});
