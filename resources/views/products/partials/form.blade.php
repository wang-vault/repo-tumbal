{{--
    Form produk, dipakai bersama oleh halaman create dan edit.
    $product bernilai null saat menambah produk baru.
--}}
@php($product = $product ?? null)

<div class="field">
    <label for="name" class="label">Nama Produk</label>
    <input type="text" id="name" name="name" class="input" required minlength="2" maxlength="120"
           value="{{ old('name', $product->name ?? '') }}">
    @error('name')
        <p class="field-error">{{ $message }}</p>
    @enderror
    <p class="hint">2-120 karakter. Ini judul yang dibaca pembeli di katalog.</p>
</div>

<div class="field">
    <label for="price" class="label">Harga (Rupiah)</label>
    <input type="number" id="price" name="price" class="input" required
           min="1000" max="100000000" step="1000"
           value="{{ old('price', $product->price ?? '') }}">
    @error('price')
        <p class="field-error">{{ $message }}</p>
    @enderror
    <p class="hint">
        Angka penuh tanpa titik atau desimal: <code>45000</code> = Rp45.000.
        Minimal Rp1.000, maksimal Rp100.000.000.
    </p>
</div>

<div class="field">
    <label for="description" class="label">Deskripsi</label>
    <textarea id="description" name="description" class="input" rows="5"
              maxlength="2000">{{ old('description', $product->description ?? '') }}</textarea>
    @error('description')
        <p class="field-error">{{ $message }}</p>
    @enderror
    <p class="hint">Boleh kosong. Jelaskan isi paket, bahan, ukuran, atau cara pakai.</p>
</div>

<div class="field">
    <label for="image_url" class="label">URL Gambar (opsional)</label>
    <input type="url" id="image_url" name="image_url" class="input"
           placeholder="https://contoh.com/foto-produk.jpg" maxlength="2048"
           value="{{ old('image_url', $product->image_url ?? '') }}">
    @error('image_url')
        <p class="field-error">{{ $message }}</p>
    @enderror
    <p class="hint">
        Kosongkan kalau belum ada foto — kartu produk otomatis menampilkan inisial nama produk.
    </p>
</div>

<div class="field">
    {{-- Input hidden menjamin is_active selalu terkirim, sehingga old() tidak
         keliru menandai kotak ini saat validasi gagal. --}}
    <input type="hidden" name="is_active" value="0">
    <label class="checkbox" for="is_active">
        <input type="checkbox" id="is_active" name="is_active" value="1"
               {{ old('is_active', $product->is_active ?? true) ? 'checked' : '' }}>
        <span>Tampilkan di katalog pembeli (status aktif)</span>
    </label>
    @error('is_active')
        <p class="field-error">{{ $message }}</p>
    @enderror
    <p class="hint">Produk nonaktif hanya terlihat di daftar kelola, tidak muncul di beranda.</p>
</div>
