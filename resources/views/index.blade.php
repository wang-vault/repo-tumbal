<x-layouts.app>
    <div class="container-x stack">

        {{-- ============================================================ --}}
        {{-- Hero halaman depan                                            --}}
        {{-- ============================================================ --}}
        <section class="front-page-hero" aria-labelledby="front-page-title">
            <div class="front-page-copy">
                <p class="eyebrow">Berita utama · Edisi hari ini</p>
                <h1 id="front-page-title" class="front-page-title">
                    Belanja gampang, <em>kabar</em> pembayaran datang cepat.
                </h1>
                <p class="front-page-deck">
                    Pilih barang favoritmu, selesaikan pembayaran bersama penjual lewat WhatsApp, dan
                    biarkan kami mengurus kabar berikutnya. Semua proses dibuat singkat, jelas, dan
                    terasa seperti halaman depan yang menyenangkan.
                </p>

                <div class="hero-actions">
                    <a href="{{ route('product-list') }}" class="btn-primary">Baca Katalog →</a>
                    <a href="{{ route('about') }}" class="btn-secondary">Cara Bayar →</a>
                </div>

                <div class="hero-facts" aria-label="Keunggulan toko">
                    <div class="hero-fact">
                        <strong>Manual</strong>
                        <span>Cara bayar tunggal</span>
                    </div>
                    <div class="hero-fact">
                        <strong>WhatsApp</strong>
                        <span>Detail pembayaran</span>
                    </div>
                    <div class="hero-fact">
                        <strong>WhatsApp</strong>
                        <span>Kabar pesanan</span>
                    </div>
                </div>
            </div>

            <aside class="hero-brief" aria-label="Ringkasan layanan">
                <p class="hero-brief-label">Headline layanan</p>
                <div>
                    <p class="hero-brief-title">Pesan.<br>Transfer.<br>Selesai.</p>
                    <p class="hero-brief-copy">
                        Satu metode bayar, tanpa bingung memilih: buat pesanan, chat penjual lewat
                        WhatsApp (pesan sudah berisi kode order &amp; nominal), bayar, lalu penjual
                        memverifikasi mutasinya.
                    </p>
                </div>
            </aside>
        </section>

        {{-- ============================================================ --}}
        {{-- Teaser alat gratis: Downloader                                --}}
        {{-- ============================================================ --}}
        <section class="tool-teaser" aria-labelledby="downloader-tool-title">
            <div>
                <p class="section-kicker">Alat gratis · Tanpa login</p>
                <h2 id="downloader-tool-title" class="tool-teaser-title">Downloader</h2>
                <p class="tool-teaser-text">
                    Mau menyimpan video dari media sosial? Buka halaman downloader, pilih platformnya,
                    lalu tempel tautannya — gratis dan tidak perlu akun.
                </p>
                <ul class="tool-teaser-list" aria-label="Platform yang didukung">
                    <li>TikTok</li>
                    <li>YouTube</li>
                    <li>Instagram</li>
                </ul>
            </div>
            <div class="tool-teaser-action">
                <a href="{{ route('downloader') }}" class="btn-primary">Buka Downloader →</a>
                <span class="tool-teaser-note">3 platform · pilih di halaman berikutnya</span>
            </div>
        </section>

        {{-- ============================================================ --}}
        {{-- Cara kerja                                                    --}}
        {{-- ============================================================ --}}
        <section aria-labelledby="how-it-works-title">
            <div class="section-heading">
                <div>
                    <p class="section-kicker">Cara kerja</p>
                    <h2 id="how-it-works-title" class="section-title">
                        Tiga langkah, satu pengalaman ringan.
                    </h2>
                </div>
                <span class="section-heading-note hide-small">Panduan pembaca</span>
            </div>

            <div class="steps-grid">
                <article class="step-card">
                    <span class="step-number" aria-hidden="true">01</span>
                    <div>
                        <h3 class="step-title">Pilih berita utama</h3>
                        <p class="step-copy">
                            Buka katalog, baca detailnya, lalu pilih produk yang paling cocok.
                        </p>
                    </div>
                </article>
                <article class="step-card">
                    <span class="step-number" aria-hidden="true">02</span>
                    <div>
                        <h3 class="step-title">Bayar lewat WhatsApp</h3>
                        <p class="step-copy">
                            Chat penjual dengan tombol sekali klik (kode order &amp; nominal sudah terisi),
                            transfer sesuai petunjuk, lalu konfirmasi di website.
                        </p>
                    </div>
                </article>
                <article class="step-card">
                    <span class="step-number" aria-hidden="true">03</span>
                    <div>
                        <h3 class="step-title">Pesanan diberitakan</h3>
                        <p class="step-copy">
                            Penjual memproses pesanan dan mengabarkan kabar baik lewat WhatsApp.
                        </p>
                    </div>
                </article>
            </div>
        </section>

        {{-- ============================================================ --}}
        {{-- Produk terbaru (dari database)                                --}}
        {{-- ============================================================ --}}
        <section aria-labelledby="featured-title">
            <div class="section-heading">
                <div>
                    <p class="section-kicker">Katalog hari ini</p>
                    <h2 id="featured-title" class="section-title">Produk terbaru</h2>
                </div>
                <a href="{{ route('product-list') }}" class="section-link">Lihat semua →</a>
            </div>

            <div class="product-grid">
                @forelse ($products as $product)
                    <article class="product-card">
                        <div class="product-card-media">
                            @if ($product->image_url)
                                <img src="{{ $product->image_url }}" alt="{{ $product->name }}">
                            @else
                                <div class="product-placeholder" aria-hidden="true">
                                    {{ mb_strtoupper(mb_substr($product->name, 0, 1)) }}
                                </div>
                            @endif
                            <span class="product-card-ribbon">{{ $product->status_label }}</span>
                        </div>
                        <div class="product-card-body">
                            <p class="product-card-kicker">Edisi katalog</p>
                            <h3 class="product-card-title">{{ $product->name }}</h3>
                            @if ($product->description)
                                <p class="product-card-snippet">{{ Str::limit($product->description, 90) }}</p>
                            @endif
                            <p class="product-card-price">{{ $product->formatted_price }}</p>
                            <div class="product-card-footer">
                                <span>{{ $product->created_at->translatedFormat('j F Y') }}</span>
                                <a href="{{ route('product-show', $product) }}" class="product-card-arrow"
                                   aria-label="Detail {{ $product->name }}">Detail →</a>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="empty-state">
                        <p class="empty-state-title">Katalog masih kosong</p>
                        <p class="empty-state-copy">
                            Jalankan <code>php artisan migrate --seed</code> untuk mengisi contoh produk,
                            atau tambah sendiri lewat tombol di bawah.
                        </p>
                        @auth
                            <a href="{{ route('product-create') }}" class="btn-primary">Tambah Produk Pertama →</a>
                        @else
                            <a href="{{ route('login') }}" class="btn-secondary">Masuk sebagai penjual →</a>
                        @endauth
                    </div>
                @endforelse
            </div>
        </section>
    </div>
</x-layouts.app>
