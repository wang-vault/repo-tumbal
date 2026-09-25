<x-layouts.app title="Tambah Produk">
    <div class="container-x stack">

        <div class="section-heading">
            <div>
                <p class="section-kicker">Katalog · Produk baru</p>
                <h1 class="section-title">Tambah Produk</h1>
            </div>
            <a href="{{ route('product-list') }}" class="section-link">← Kembali ke daftar</a>
        </div>

        <form action="{{ route('product-store') }}" method="post" class="card form-card">
            @csrf

            @include('products.partials.form')

            <div class="form-actions">
                <button type="submit" class="btn-primary">Simpan Produk</button>
                <a href="{{ route('product-list') }}" class="btn-secondary">Batal</a>
            </div>
        </form>
    </div>
</x-layouts.app>
