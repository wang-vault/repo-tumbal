<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Halaman publik
|--------------------------------------------------------------------------
| Katalog dan detail produk boleh dibaca siapa saja — ini etalase toko.
*/

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/about', [HomeController::class, 'about'])->name('about');
Route::get('/downloader', [HomeController::class, 'downloader'])->name('downloader');
Route::get('/products', [ProductController::class, 'index'])->name('product-list');

/*
|--------------------------------------------------------------------------
| Khusus penjual yang sudah masuk
|--------------------------------------------------------------------------
| PENTING: kelompok ini harus didaftarkan SEBELUM `/products/{product}`,
| kalau tidak "create" dan "edit" akan ditangkap sebagai parameter {product}.
*/

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/products/create', [ProductController::class, 'create'])->name('product-create');
    Route::post('/products', [ProductController::class, 'store'])->name('product-store');
    Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->name('product-edit');
    Route::put('/products/{product}', [ProductController::class, 'update'])->name('product-update');
    Route::delete('/products/{product}', [ProductController::class, 'destroy'])->name('product-destroy');
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
| (lihat redirectUsersTo di bootstrap/app.php).
*/

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});
