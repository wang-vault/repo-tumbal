<x-layouts.app title="Terlalu banyak permintaan">
    @include('errors.partials.notice', [
        'code' => 429,
        'title' => 'Pelan-pelan dulu',
        'message' => 'Anda mengirim terlalu banyak perubahan dalam waktu singkat. Batasnya 20 tulisan per menit untuk tiap penjual. Tunggu sebentar, lalu lanjutkan lagi.',
        'refresh' => true,
    ])
</x-layouts.app>
