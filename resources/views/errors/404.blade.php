<x-layouts.app title="Halaman tidak ditemukan">
    @include('errors.partials.notice', [
        'code' => 404,
        'title' => 'Halaman tidak ditemukan',
        'message' => 'Alamat yang Anda buka tidak ada di toko ini. Produknya mungkin sudah dihapus penjual, atau tautannya salah ketik. Coba cari dari katalog.',
    ])
</x-layouts.app>
