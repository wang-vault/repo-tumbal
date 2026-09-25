<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configurePagination();
        $this->configureRateLimiting();
    }

    /**
     * Tampilan pagination bawaan Laravel penuh class Tailwind, sedangkan proyek
     * ini sengaja tidak menjalankan `npm install` / `npm run build`. Jadi view
     * sendiri yang dipakai, mengikuti gaya koran di public/css/anubis.css.
     */
    private function configurePagination(): void
    {
        Paginator::defaultView('partials.pagination');
    }

    /**
     * Batas laju per kelompok aksi:
     *
     *   product-write  20/menit per penjual  tambah, ubah, hapus produk
     *   order-create   10/menit per IP       membuat pesanan (rute publik, tanpa login)
     *   order-claim     5/menit per IP       menekan "saya sudah transfer"
     *   order-status   30/menit per penjual  mengubah status pesanan
     *
     * Login punya batasannya sendiri di LoginController: 5 percobaan per menit
     * untuk tiap kombinasi email + IP. Pelanggaran batas di sini menghasilkan 429
     * yang dirender resources/views/errors/429.blade.php.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('product-write', function (Request $request) {
            return Limit::perMinute(20)->by($this->sellerKey($request));
        });

        RateLimiter::for('order-status', function (Request $request) {
            return Limit::perMinute(30)->by($this->sellerKey($request));
        });

        // Rute pemesanan terbuka untuk tamu, jadi kuncinya alamat IP.
        RateLimiter::for('order-create', fn (Request $request) => Limit::perMinute(10)->by((string) $request->ip()));
        RateLimiter::for('order-claim', fn (Request $request) => Limit::perMinute(5)->by((string) $request->ip()));
    }

    /**
     * Kunci limiter untuk aksi penjual: id penggunanya, jatuh ke alamat IP kalau
     * (seharusnya tidak pernah terjadi) middleware `auth` belum menyaring permintaannya.
     */
    private function sellerKey(Request $request): string
    {
        return (string) ($request->user()?->getAuthIdentifier() ?? $request->ip());
    }
}
