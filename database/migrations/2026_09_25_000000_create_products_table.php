<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel katalog. Batasan nilainya (panjang nama & rentang harga) ditegakkan
     * di ProductController::validated(), meniru CHECK constraint milik Anubis.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');                 // 2-120 karakter
            $table->text('description')->nullable();
            $table->unsignedBigInteger('price');    // Rupiah penuh: 1.000 s.d. 100.000.000
            $table->string('image_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Katalog publik: produk aktif, terbaru lebih dulu.
            $table->index(['is_active', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
