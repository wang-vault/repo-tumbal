<x-layouts.app title="Katalog Produk">
    <div class="container-x stack">

        <div class="catalog-intro">
            <p class="section-kicker">Katalog · Kelola produk</p>
            <h1 class="catalog-title">Daftar Produk</h1>
            <p class="catalog-deck">
                Semua barang yang dijual toko ini. Produk berstatus <strong>nonaktif</strong> tetap
                tersimpan di daftar ini (untuk arsip), tetapi tidak muncul di beranda pembeli.
            </p>

            {{-- Pencarian memakai form GET (?q=...) supaya hasilnya bisa di-bookmark
                 dan tetap jalan tanpa JavaScript. --}}
            <form action="{{ route('product-list') }}" method="get" class="search-row">
                <div class="search-bar">
                    <input type="search" name="q" value="{{ $search }}" class="input search-bar-input"
                           placeholder="Cari nama atau deskripsi produk…" aria-label="Kata kunci pencarian">
                    <button type="submit" class="btn-primary">Cari</button>
                    @if ($search !== '')
                        <a href="{{ route('product-list') }}" class="btn-secondary">Hapus</a>
                    @endif
                </div>
                <p class="search-count">
                    <strong>{{ $products->count() }}</strong>
                    produk{{ $search !== '' ? ' untuk "'.$search.'"' : '' }}
                </p>
            </form>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Nama</th>
                        <th>Harga</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($products as $product)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>
                                <a href="{{ route('product-show', $product) }}" class="paper-link">
                                    {{ $product->name }}
                                </a>
                            </td>
                            <td class="col-price">{{ $product->formatted_price }}</td>
                            <td>
                                <span class="badge {{ $product->is_active ? 'badge-ok' : 'badge-off' }}">
                                    {{ $product->status_label }}
                                </span>
                            </td>
                            <td>
                                <div class="row-actions">
                                    <a href="{{ route('product-show', $product) }}">Detail</a>
                                    <span class="sep">|</span>
                                    <a href="{{ route('product-edit', $product) }}">Edit</a>
                                    <span class="sep">|</span>
                                    <form action="{{ route('product-destroy', $product) }}" method="post" class="inline-form">
                                        @csrf
                                        @method('delete')
                                        {{-- @js() menghasilkan string JS berkutip tunggal (kutip di dalam
                                             nama di-hex-escape), jadi atributnya harus berkutip ganda. --}}
                                        <button type="submit" class="link-danger"
                                                onclick="return confirm(@js("Yakin hapus {$product->name}?"))">Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="table-empty">
                                @if ($search !== '')
                                    Tidak ada produk yang cocok dengan "{{ $search }}".
                                @else
                                    Belum ada produk. Jalankan <code>php artisan migrate --seed</code>
                                    atau tambah produk baru lewat tombol di bawah.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="form-actions">
            <a href="{{ route('product-create') }}" class="btn-primary">+ Tambah Produk</a>
            <a href="{{ route('home') }}" class="btn-secondary">← Kembali ke Beranda</a>
        </div>
    </div>
</x-layouts.app>
