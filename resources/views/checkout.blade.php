<x-layouts.app title="Pesan {{ $product->name }}">
    <div class="container-x stack">

        <div class="section-heading">
            <div>
                <p class="section-kicker">Checkout · Tanpa perlu masuk</p>
                <h1 class="section-title">Pesan {{ $product->name }}</h1>
            </div>
            <a href="{{ route('product-show', $product) }}" class="section-link">← Kembali ke produk</a>
        </div>

        <div class="detail-grid">

            <form action="{{ route('order-store', $product) }}" method="post" class="card form-card">
                @csrf

                <div class="field">
                    <label for="buyer_name" class="label">Nama Pemesan</label>
                    <input type="text" id="buyer_name" name="buyer_name" class="input" required
                           minlength="2" maxlength="80" autocomplete="name"
                           value="{{ old('buyer_name') }}">
                    @error('buyer_name')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                    <p class="hint">Nama ini yang dipakai penjual menyapa kamu, dan dipendekkan jadi "Budi S." kalau pesananmu tampil di halaman testimoni.</p>
                </div>

                <div class="field">
                    <label for="buyer_whatsapp" class="label">Nomor WhatsApp</label>
                    <input type="tel" id="buyer_whatsapp" name="buyer_whatsapp" class="input" required
                           minlength="8" maxlength="20" autocomplete="tel" inputmode="tel"
                           placeholder="081234567890"
                           value="{{ old('buyer_whatsapp') }}">
                    @error('buyer_whatsapp')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                    <p class="hint">
                        Pembayaran dikoordinasikan lewat chat. Nomor disimpan dalam bentuk
                        <code>62812…</code> — awalan <code>0</code>, <code>+62</code>, spasi, atau tanda hubung
                        boleh dipakai, semuanya dirapikan otomatis.
                    </p>
                </div>

                <div class="field">
                    <label for="buyer_email" class="label">Email (opsional)</label>
                    <input type="email" id="buyer_email" name="buyer_email" class="input" maxlength="255"
                           autocomplete="email" placeholder="nama@contoh.com"
                           value="{{ old('buyer_email') }}">
                    @error('buyer_email')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                    <p class="hint">Boleh dikosongkan. Tidak dipakai untuk mengirim apa pun di versi ini.</p>
                </div>

                <div class="field">
                    <label for="quantity" class="label">Jumlah</label>
                    <input type="number" id="quantity" name="quantity" class="input" required
                           min="1" max="{{ \App\Models\Order::MAX_QUANTITY }}" step="1"
                           value="{{ old('quantity', 1) }}">
                    @error('quantity')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                    <p class="hint">Maksimal {{ \App\Models\Order::MAX_QUANTITY }} pcs per pesanan. Totalnya dihitung dari harga di sebelah kanan.</p>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-primary">Buat Pesanan</button>
                    <a href="{{ route('product-list') }}" class="btn-secondary">Batal</a>
                </div>
            </form>

            <aside class="card checkout-summary">
                <p class="detail-heading">Ringkasan</p>

                <dl class="detail-meta">
                    <div>
                        <dt>Produk</dt>
                        <dd>{{ $product->name }}</dd>
                    </div>
                    <div>
                        <dt>Harga satuan</dt>
                        <dd>{{ $product->formatted_price }}</dd>
                    </div>
                    <div>
                        <dt>Total</dt>
                        <dd>harga satuan × jumlah</dd>
                    </div>
                </dl>

                <p class="detail-heading">Cara bayarnya</p>
                <ol class="checkout-steps">
                    <li>Isi form ini, lalu tekan <strong>Buat Pesanan</strong>.</li>
                    <li>Kamu dapat kode pesanan <code>ORD-…</code>. Simpan — kode itu kunci halaman pesananmu.</li>
                    <li>Hubungi penjual di WhatsApp untuk meminta rekening/QRIS, lalu transfer.</li>
                    <li>Tekan <strong>Saya sudah transfer</strong> di halaman pesanan, sertakan nama pengirim atau nomor referensinya.</li>
                    <li>Penjual memeriksa mutasi dan menaikkan status sampai <strong>Selesai</strong>.</li>
                </ol>

                <p class="hint">
                    Harga dan nama produk dibekukan ke dalam pesanan, jadi perubahan harga
                    setelah ini tidak mengubah tagihanmu.
                </p>
            </aside>

        </div>
    </div>
</x-layouts.app>
