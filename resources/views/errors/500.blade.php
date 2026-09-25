<x-layouts.app title="Terjadi kesalahan">
    @include('errors.partials.notice', [
        'code' => 500,
        'title' => 'Ada yang tidak beres di sisi kami',
        'message' => 'Server gagal menyelesaikan permintaan ini. Rincian kesalahannya tercatat di storage/logs/laravel.log. Silakan coba lagi beberapa saat.',
    ])
</x-layouts.app>
