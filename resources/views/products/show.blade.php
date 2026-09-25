<x-layouts.app title="{{ $product->name }}">
    <div class="container-x stack">

        <div class="section-heading">
            <div>
                <p class="section-kicker">Katalog · Detail produk</p>
                <h1 class="section-title">{{ $product->name }}</h1>
            </div>
            <a href="{{ route('product-list') }}" class="section-link">← Kembali ke daftar</a>
        </div>

        <div class="detail-grid">
            <div class="card detail-media">
                @if ($product->image_url)
                    <img src="{{ $product->image_url }}" alt="{{ $product->name }}">
                @else
                    <div class="product-placeholder" aria-hidden="true">
                        {{ mb_strtoupper(mb_substr($product->name, 0, 1)) }}
                    </div>
                @endif
            </div>

            <div class="card detail-body">
                <p class="detail-price">{{ $product->formatted_price }}</p>

                <p class="detail-row">
                    <span class="badge {{ $product->is_active ? 'badge-ok' : 'badge-off' }}">
                        {{ $product->status_label }}
                    </span>
                </p>

                <div class="detail-section">
                    <p class="detail-heading">Deskripsi</p>
                    @if ($product->description)
                        <p class="detail-copy">{{ $product->description }}</p>
                    @else
                        <p class="detail-copy detail-copy--muted">Belum ada deskripsi untuk produk ini.</p>
                    @endif
                </div>

                <div class="detail-section">
                    <p class="detail-heading">Cara bayar</p>
                    <p class="detail-copy">
                        Transfer manual yang dikoordinasikan lewat WhatsApp — penjual mengirim detail
                        rekening/QRIS di dalam chat, pembeli transfer dengan nominal + kode unik, lalu
                        penjual memverifikasi mutasinya.
                    </p>
                </div>

                <dl class="detail-meta">
                    <div>
                        <dt>ID Produk</dt>
                        <dd>#{{ $product->id }}</dd>
                    </div>
                    <div>
                        <dt>Dibuat</dt>
                        <dd>{{ $product->created_at->translatedFormat('j F Y, H:i') }}</dd>
                    </div>
                    <div>
                        <dt>Terakhir diubah</dt>
                        <dd>{{ $product->updated_at->translatedFormat('j F Y, H:i') }}</dd>
                    </div>
                </dl>

                @if ($product->is_active)
                    <div class="form-actions">
                        <a href="{{ route('checkout', $product) }}" class="btn-primary">Pesan Produk Ini →</a>
                        @auth
                            <a href="{{ route('product-edit', $product) }}" class="btn-secondary">Ubah Produk</a>
                            <form action="{{ route('product-destroy', $product) }}" method="post" class="inline-form">
                                @csrf
                                @method('delete')
                                <button type="submit" class="btn-danger"
                                        onclick="return confirm(@js("Yakin hapus {$product->name}?"))">Hapus Produk</button>
                            </form>
                        @endauth
                    </div>
                @else
                    <div class="form-actions">
                        <p class="detail-copy detail-copy--muted">
                            Produk ini sedang nonaktif, jadi tidak bisa dipesan.
                            @auth
                                <a href="{{ route('product-edit', $product) }}" class="paper-link">Aktifkan lagi</a>
                            @else
                                <a href="{{ route('product-list') }}" class="paper-link">Lihat produk lain</a>
                            @endauth
                        </p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-layouts.app>
