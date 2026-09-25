<x-layouts.app title="Akses ditolak">
    @include('errors.partials.notice', [
        'code' => 403,
        'title' => 'Akses ditolak',
        'message' => 'Anda tidak punya izin membuka halaman ini. Halaman tambah, ubah, dan hapus produk hanya untuk penjual yang sudah masuk.',
        'login' => true,
    ])
</x-layouts.app>
