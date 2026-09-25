<x-layouts.app title="Testimoni">
    <div class="container-x stack">

        <div class="section-heading">
            <div>
                <p class="section-kicker">Testimoni · Otomatis dari pesanan selesai</p>
                <h1 class="section-title">Kabar dari Pembeli</h1>
            </div>
            <a href="{{ route('product-list') }}" class="section-link">Lihat katalog →</a>
        </div>

        <div class="card testimoni-note">
            <p class="hint">
                Halaman ini diisi otomatis dari
                <strong>{{ $total }}</strong> pesanan berstatus <strong>Selesai</strong>@if ($total > $limit),
                ditampilkan {{ $limit }} yang paling baru @endif — bukan ulasan yang diketik pembeli,
                jadi tidak ada yang bisa menyuntingnya dari sini.
            </p>
            <p class="hint">
                Demi privasi, nama pembeli dipendekkan ("Budi S."). Kode pesanan, nomor WhatsApp,
                dan nominal tidak pernah diambil dari database untuk halaman ini.
            </p>
        </div>

        @forelse ($items as $item)
            @if ($loop->first)
                <div class="testimoni-grid">
            @endif

            <article class="card testimoni-card">
                <p class="testimoni-mark" aria-hidden="true">“</p>
                <p class="testimoni-product">{{ $item->product_name_snapshot }}</p>
                <p class="testimoni-meta">
                    <strong>{{ $item->masked_buyer_name }}</strong>
                    <span class="sep">·</span> {{ $item->quantity }} pcs
                    <span class="sep">·</span> selesai {{ $item->updated_at->translatedFormat('j F Y') }}
                </p>
            </article>

            @if ($loop->last)
                </div>
            @endif
        @empty
            <div class="card empty-state">
                <p class="empty-state-title">Belum ada testimoni</p>
                <p class="empty-state-copy">
                    Testimoni muncul otomatis begitu ada pesanan yang ditandai
                    <strong>Selesai</strong> oleh penjual.
                </p>
                <a href="{{ route('product-list') }}" class="btn-primary">Lihat katalog dulu →</a>
            </div>
        @endforelse

        <div class="form-actions">
            <a href="{{ route('home') }}" class="btn-secondary">← Kembali ke Beranda</a>
            <a href="{{ route('about') }}" class="btn-secondary">Cara bayar</a>
        </div>
    </div>
</x-layouts.app>
