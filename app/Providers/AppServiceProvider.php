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
     * Batasi penulisan produk (tambah, ubah, hapus) sampai 20 kali per menit
     * untuk tiap penjual — cukup longgar untuk mengisi katalog dengan tangan,
     * cukup ketat supaya form tidak bisa dipakai membanjiri tabel.
     *
     * Login punya batasannya sendiri di LoginController: 5 percobaan per menit
     * untuk tiap kombinasi email + IP.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('product-write', function (Request $request) {
            $key = $request->user()?->getAuthIdentifier() ?? $request->ip();

            return Limit::perMinute(20)->by((string) $key);
        });
    }
}
