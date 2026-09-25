@props(['title' => null])
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="description" content="Anubis Store versi Laravel — toko online ringan dengan satu metode bayar: transfer manual yang dikoordinasikan lewat WhatsApp.">
    <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name', 'Anubis Store') }}</title>

    {{-- CSS statis di public/css — tidak perlu `npm run build`, cukup `php artisan serve`. --}}
    <link rel="stylesheet" href="{{ asset('css/anubis.css') }}">
</head>
<body>
    <header class="masthead">
        <div class="container-x">
            <div class="masthead-meta">
                <span>Edisi harian · Belanja lokal</span>
                <span class="hide-small">Bayar transfer manual via WhatsApp · pesanan diantar penjual</span>
            </div>

            <div class="masthead-brand-row">
                <a href="{{ route('home') }}" class="masthead-brand-link" aria-label="Kembali ke beranda">
                    <span class="masthead-monogram" aria-hidden="true">AN</span>
                    <span class="masthead-brand-text">
                        <span class="masthead-wordmark">{{ config('app.name', 'Anubis Store') }}</span>
                        <span class="masthead-subtitle">Kabar belanja hari ini</span>
                    </span>
                </a>

                <nav class="masthead-actions" aria-label="Aksi akun">
                    @auth
                        <a href="{{ route('product-create') }}" class="btn-primary btn-sm">Tambah Produk</a>
                        <span class="masthead-user">{{ auth()->user()->name }}</span>
                        <form action="{{ route('logout') }}" method="post" class="inline-form">
                            @csrf
                            <button type="submit" class="btn-secondary btn-sm">Keluar</button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="btn-secondary btn-sm">Masuk</a>
                    @endauth
                </nav>
            </div>

            <nav class="masthead-nav-row" aria-label="Navigasi utama">
                <a href="{{ route('home') }}"
                   class="masthead-nav-link {{ request()->routeIs('home') ? 'is-active' : '' }}">Beranda</a>
                <a href="{{ route('product-list') }}"
                   class="masthead-nav-link {{ request()->routeIs('product-*') ? 'is-active' : '' }}">Katalog Produk</a>
                <a href="{{ route('testimoni') }}"
                   class="masthead-nav-link {{ request()->routeIs('testimoni') ? 'is-active' : '' }}">Testimoni</a>
                @auth
                    <a href="{{ route('order-list') }}"
                       class="masthead-nav-link {{ request()->routeIs('order-*') ? 'is-active' : '' }}">Pesanan</a>
                @endauth
                <a href="{{ route('about') }}"
                   class="masthead-nav-link {{ request()->routeIs('about') ? 'is-active' : '' }}">Tentang</a>
                <span class="masthead-nav-edition">Edisi No. 01</span>
                <a href="{{ route('downloader') }}"
                   class="masthead-nav-link masthead-nav-link--tool {{ request()->routeIs('downloader') ? 'is-active' : '' }}">
                    <span class="masthead-nav-icon" aria-hidden="true">↓</span>
                    Downloader
                </a>
            </nav>
        </div>
    </header>

    <main class="site-main">
        @if (session('success'))
            <div class="container-x">
                <p class="alert-info">{{ session('success') }}</p>
            </div>
        @endif

        {{-- Pesan flash untuk tindakan yang ditolak (misalnya produk yang sudah
             pernah dipesan tidak boleh dihapus, atau perpindahan status yang
             tidak diizinkan). Class .alert-warn sudah ada di anubis.css. --}}
        @if (session('error'))
            <div class="container-x">
                <p class="alert-warn">{{ session('error') }}</p>
            </div>
        @endif

        {{-- $errors tidak selalu tersedia: halaman error untuk alamat yang tidak
             dikenali dirender sebelum middleware ShareErrorsFromSession sempat
             berjalan, jadi variabelnya belum dibagi ke view. Tanpa pemeriksaan
             ini, halaman 404 malah berubah jadi 500. --}}
        @if (($errors ?? null)?->any())
            <div class="container-x">
                <div class="alert-error">
                    <strong>Periksa kembali isianmu:</strong>
                    <ul class="alert-list">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        {{ $slot }}
    </main>

    <footer class="site-footer">
        <div class="container-x site-footer-grid">
            <div>
                <p class="site-footer-masthead">Kabar Toko</p>
                <p class="site-footer-copy">
                    Belanja ringkas dengan rasa koran pagi: pilih produk, selesaikan transfer manual lewat
                    WhatsApp bersama penjual, lalu biarkan penjual mengabarkan pesananmu.
                </p>
            </div>
            <div>
                <p class="site-footer-heading">Jelajahi</p>
                <div class="site-footer-links">
                    <a href="{{ route('home') }}" class="site-footer-link">Beranda</a>
                    <a href="{{ route('product-list') }}" class="site-footer-link">Katalog produk</a>
                    <a href="{{ route('testimoni') }}" class="site-footer-link">Testimoni</a>
                    @auth
                        <a href="{{ route('order-list') }}" class="site-footer-link">Kelola pesanan</a>
                        <a href="{{ route('product-create') }}" class="site-footer-link">Tambah produk</a>
                    @endauth
                    <a href="{{ route('downloader') }}" class="site-footer-link">Downloader</a>
                </div>
            </div>
            <div>
                <p class="site-footer-heading">Toko ini</p>
                <div class="site-footer-links">
                    <a href="{{ route('about') }}" class="site-footer-link">Cara bayar</a>
                    @auth
                        <form action="{{ route('logout') }}" method="post" class="inline-form">
                            @csrf
                            <button type="submit" class="site-footer-link site-footer-button">Keluar</button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="site-footer-link">Masuk</a>
                    @endauth
                    <a href="https://github.com/wang-vault/anubis" class="site-footer-link" rel="noopener">Versi Next.js (asli)</a>
                    <a href="https://laravel.com/docs" class="site-footer-link" rel="noopener">Dokumentasi Laravel</a>
                </div>
            </div>
        </div>
        <div class="container-x site-footer-base">
            <span>Dibuat dengan Laravel {{ Illuminate\Foundation\Application::VERSION }}</span>
            <span>Satu seller · satu metode bayar · tanpa keranjang</span>
        </div>
    </footer>
</body>
</html>
