<x-layouts.app title="Downloader">
    <div class="container-x stack">

        <div class="catalog-intro">
            <p class="section-kicker">Alat gratis · Tanpa login</p>
            <h1 class="catalog-title">Downloader</h1>
            <p class="catalog-deck">
                Pilih platformnya, tempel tautan video, lalu simpan. Di aplikasi Anubis versi Next.js
                alat ini benar-benar mengunduh; pada port Laravel ini halamannya statis sebagai
                contoh tampilan.
            </p>
        </div>

        <div class="downloader-grid">
            @foreach ([
                ['platform' => 'TikTok', 'tone' => '#1e1c19', 'hint' => 'Video MP4 tanpa watermark', 'label' => 'T'],
                ['platform' => 'YouTube', 'tone' => '#a61e2b', 'hint' => 'Video atau audio MP3', 'label' => 'Y'],
                ['platform' => 'Instagram', 'tone' => '#d3942b', 'hint' => 'Reels, foto, dan video', 'label' => 'I'],
            ] as $tool)
                <article class="downloader-card" style="--tone: {{ $tool['tone'] }}">
                    <span class="downloader-monogram" aria-hidden="true">{{ $tool['label'] }}</span>
                    <div>
                        <h2 class="downloader-title">{{ $tool['platform'] }}</h2>
                        <p class="downloader-hint">{{ $tool['hint'] }}</p>
                    </div>
                    <div class="search-bar">
                        <input type="url" class="input search-bar-input" disabled
                               placeholder="Tempel tautan {{ $tool['platform'] }} di sini…"
                               aria-label="Tautan {{ $tool['platform'] }} (nonaktif)">
                        <button type="button" class="btn-secondary" disabled>Unduh</button>
                    </div>
                </article>
            @endforeach
        </div>

        <div class="form-actions">
            <a href="{{ route('home') }}" class="btn-secondary">← Kembali ke Beranda</a>
            <a href="{{ route('product-list') }}" class="btn-primary">Baca Katalog →</a>
        </div>
    </div>
</x-layouts.app>
