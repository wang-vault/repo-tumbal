<x-layouts.app title="Halaman kedaluwarsa">
    @include('errors.partials.notice', [
        'code' => 419,
        'title' => 'Halaman kedaluwarsa',
        'message' => 'Sesi atau token keamanan halaman ini sudah kedaluwarsa — biasanya karena tab dibiarkan terbuka lama. Muat ulang halaman, isi ulang formnya, lalu kirim lagi.',
        'refresh' => true,
    ])
</x-layouts.app>
