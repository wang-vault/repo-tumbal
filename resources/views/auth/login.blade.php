<x-layouts.app title="Masuk">
    <div class="container-x stack">
        <div class="auth-wrap">
            <div class="card auth-card">
                <p class="section-kicker">Area penjual</p>
                <h1 class="auth-title">Masuk</h1>
                <p class="auth-deck">
                    Masuk untuk menambah, mengubah, atau menghapus produk katalog.
                    Pembeli tidak perlu akun untuk membaca katalog dan melihat harga.
                </p>

                <form action="{{ route('login.store') }}" method="post" class="auth-form">
                    @csrf

                    <div class="field">
                        <label for="email" class="label">Email</label>
                        <input type="email" id="email" name="email" class="input" required
                               autocomplete="username" autofocus value="{{ old('email') }}">
                        @error('email')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="field">
                        <label for="password" class="label">Password</label>
                        <input type="password" id="password" name="password" class="input" required
                               autocomplete="current-password">
                        @error('password')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="field">
                        <input type="hidden" name="remember" value="0">
                        <label class="checkbox" for="remember">
                            <input type="checkbox" id="remember" name="remember" value="1"
                                   {{ old('remember') ? 'checked' : '' }}>
                            <span>Ingat saya di perangkat ini</span>
                        </label>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-primary">Masuk</button>
                        <a href="{{ route('home') }}" class="btn-secondary">← Kembali ke Beranda</a>
                    </div>
                </form>

                <p class="auth-note">
                    Akun demo hasil seeder: <code>admin@anubis.test</code> / <code>password</code>
                </p>
            </div>
        </div>
    </div>
</x-layouts.app>
