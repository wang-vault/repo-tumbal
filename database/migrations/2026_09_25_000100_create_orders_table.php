<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel pesanan — cermin yang disederhanakan dari `public.orders` milik Anubis
     * (supabase/store/001_schema.sql).
     *
     * Yang TIDAK ikut dipindahkan, karena port ini hanya memakai jalur pembayaran
     * manual (tanpa WhatsApp/Telegram/provider QRIS):
     *   - kolom provider otomatis: payment_id, payment_url, qr_image_url,
     *     charged_amount, payment_expired_at, last_payment_checked_at
     *   - kolom notifikasi: telegram_notified_at, manual_claim_notified_at
     *   - account_id (pesanan milik akun pembeli) dan manual_reviewed_by
     *
     * Sebagai gantinya `order_code` menjadi kunci akses halaman pesanan: pembeli
     * tidak perlu mendaftar, cukup menyimpan kodenya.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            // Kode publik ORD-YYYYMMDD-XXXXXX. Bukan id mentah, supaya tidak bisa
            // ditebak berurutan dan enak disebut di dalam chat.
            $table->string('order_code')->unique();

            // restrictOnDelete: produk yang sudah pernah dipesan tidak boleh
            // dihapus (riwayat pesanan harus tetap utuh) — cukup dinonaktifkan.
            $table->foreignId('product_id')->constrained()->restrictOnDelete();

            // Snapshot: nama & harga disalin saat pesanan dibuat, jadi mengubah
            // produk belakangan tidak mengubah riwayat pesanan lama.
            $table->string('product_name_snapshot');
            $table->unsignedBigInteger('unit_price_snapshot');   // Rupiah penuh
            $table->unsignedSmallInteger('quantity');            // 1-20 per pesanan
            $table->unsignedBigInteger('total_amount');          // harga satuan x jumlah

            $table->string('payment_method')->default('MANUAL');
            $table->string('payment_status')->default('PENDING');  // PENDING | PAID
            $table->string('order_status')->default('PENDING');    // PENDING | PAID | PROCESSING | DONE

            // Klaim pembeli ("saya sudah transfer") + hasil verifikasi penjual.
            $table->timestamp('manual_claim_at')->nullable();
            $table->text('manual_claim_note')->nullable();
            $table->string('manual_claim_reference')->nullable();
            $table->timestamp('manual_reviewed_at')->nullable();
            $table->string('manual_review_status')->nullable();    // APPROVED | REJECTED
            $table->text('manual_review_note')->nullable();
            $table->timestamp('paid_at')->nullable();

            // Kontak pembeli, disalin saat pesanan dibuat.
            $table->string('buyer_name_snapshot');
            $table->string('buyer_whatsapp_snapshot');             // format 62xxxxxxxxxx
            $table->string('buyer_email_snapshot')->nullable();

            $table->timestamps();

            // Daftar kelola penjual: saring berdasarkan status, urutkan terbaru.
            $table->index(['order_status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
