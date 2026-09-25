<x-layouts.app title="Katalog Produk">
    <div class="container-x stack">

        <div class="catalog-intro">
            <p class="section-kicker">
                @auth Katalog · Kelola produk @else Katalog · Belanja lokal @endauth
            </p>
            <h1 class="catalog-title">Daftar Produk</h1>
            <p class="catalog-deck">
                @auth
                    Semua barang yang dijual toko ini. Produk berstatus <strong>nonaktif</strong> tetap
                    tersimpan di daftar ini (untuk arsip), tetapi tidak muncul di beranda pembeli.
                @else
                    Semua barang yang sedang dijual toko ini. Pembayaran dilakukan lewat transfer
                    manual yang dikoordinasikan penjual di WhatsApp.
                @endauth
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
                    <strong>{{ $products->total() }}</strong>
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
                        @auth
                            <th>Status</th>
                        @endauth
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($products as $product)
                        <tr>
                            {{-- Nomor baris melanjutkan halaman sebelumnya, bukan
                                 mulai dari 1 lagi di tiap halaman. --}}
                            <td>{{ $products->firstItem() + $loop->index }}</td>
                            <td>
                                <a href="{{ route('product-show', $product) }}" class="paper-link">
                                    {{ $product->name }}
                                </a>
                            </td>
                            <td class="col-price">{{ $product->formatted_price }}</td>
                            @auth
                                <td>
                                    <span class="badge {{ $product->is_active ? 'badge-ok' : 'badge-off' }}">
                                        {{ $product->status_label }}
                                    </span>
                                </td>
                            @endauth
                            <td>
                                <div class="row-actions">
                                    <a href="{{ route('product-show', $product) }}">Detail</a>
                                    @auth
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
                                    @endauth
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="@auth 5 @else 4 @endauth" class="table-empty">
                                @if ($search !== '')
                                    Tidak ada produk yang cocok dengan "{{ $search }}".
                                @elseif ($products->currentPage() > $products->lastPage())
                                    {{-- Bisa terjadi kalau nomor halaman diketik tangan
                                         (?page=99) padahal halamannya tidak sebanyak itu. --}}
                                    Tidak ada produk di halaman ini.
                                    <a href="{{ route('product-list') }}">Kembali ke halaman pertama</a>.
                                @else
                                    Belum ada produk yang dijual.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Hanya dirender kalau halamannya lebih dari satu; tampilannya dari
             resources/views/partials/pagination.blade.php. --}}
        {{ $products->links() }}

        <div class="form-actions">
            @auth
                <a href="{{ route('product-create') }}" class="btn-primary">+ Tambah Produk</a>
            @else
                <a href="{{ route('login') }}" class="btn-secondary">Masuk sebagai penjual</a>
            @endauth
            <a href="{{ route('home') }}" class="btn-secondary">← Kembali ke Beranda</a>
        </div>
    </div>
</x-layouts.app>
