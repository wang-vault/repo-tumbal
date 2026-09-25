<x-layouts.app title="Ubah Produk">
    <div class="container-x stack">

        <div class="section-heading">
            <div>
                <p class="section-kicker">Katalog · Sunting</p>
                <h1 class="section-title">Ubah Produk</h1>
            </div>
            <a href="{{ route('product-show', $product) }}" class="section-link">Lihat detail →</a>
        </div>

        <form action="{{ route('product-update', $product) }}" method="post" class="card form-card">
            @csrf
            @method('put')

            @include('products.partials.form')

            <div class="form-actions">
                <button type="submit" class="btn-primary">Simpan Perubahan</button>
                <a href="{{ route('product-list') }}" class="btn-secondary">Batal</a>
            </div>
        </form>
    </div>
</x-layouts.app>
