<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Halaman statis
|--------------------------------------------------------------------------
| /products/create harus didaftarkan SEBELUM /products/{product}, kalau tidak
| "create" akan ditangkap sebagai id produk.
*/

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/about', [HomeController::class, 'about'])->name('about');
Route::get('/downloader', [HomeController::class, 'downloader'])->name('downloader');

/*
|--------------------------------------------------------------------------
| CRUD produk (katalog)
|--------------------------------------------------------------------------
*/

Route::get('/products', [ProductController::class, 'index'])->name('product-list');
Route::get('/products/create', [ProductController::class, 'create'])->name('product-create');
Route::post('/products', [ProductController::class, 'store'])->name('product-store');
Route::get('/products/{product}', [ProductController::class, 'show'])->name('product-show');
Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->name('product-edit');
Route::put('/products/{product}', [ProductController::class, 'update'])->name('product-update');
Route::delete('/products/{product}', [ProductController::class, 'destroy'])->name('product-destroy');
