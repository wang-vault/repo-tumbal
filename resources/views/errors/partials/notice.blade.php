{{--
    Kerangka bersama halaman error. Dipakai errors/403, 404, 419, 429, 500 lewat
    @include('errors.partials.notice', [...]).

    Variabel:
      $code     angka status HTTP yang dipajang besar
      $title    judul singkat
      $message  penjelasan untuk pembaca + saran tindakan
      $login    (opsional) tampilkan tombol "Masuk sebagai penjual"
      $refresh  (opsional) tampilkan tombol muat ulang halaman yang sama
--}}
<div class="container-x stack">
    <div class="error-card">
        <p class="error-code" aria-hidden="true">{{ $code }}</p>

        <div class="error-body">
            <p class="section-kicker">Anubis Store · Kabar kecil</p>
            <h1 class="error-title">{{ $title }}</h1>
            <p class="error-message">{{ $message }}</p>

            <div class="form-actions">
                <a href="{{ route('home') }}" class="btn-primary">← Kembali ke Beranda</a>
                <a href="{{ route('product-list') }}" class="btn-secondary">Lihat Katalog</a>
                @if ($login ?? false)
                    <a href="{{ route('login') }}" class="btn-secondary">Masuk sebagai penjual</a>
                @endif
                @if ($refresh ?? false)
                    <button type="button" class="btn-secondary" onclick="window.location.reload()">
                        Muat ulang halaman
                    </button>
                @endif
            </div>
        </div>
    </div>
</div>
