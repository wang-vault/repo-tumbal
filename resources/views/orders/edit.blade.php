@php
    use App\Models\Order;

    $sudahNaik = $order->order_status !== Order::STATUS_PENDING;
@endphp

<x-layouts.app title="Ubah Pesanan {{ $order->order_code }}">
    <div class="container-x stack">

        <div class="section-heading">
            <div>
                <p class="section-kicker">Pesanan · Sunting</p>
                <h1 class="section-title">Ubah Pesanan</h1>
            </div>
            <a href="{{ route('order-show', $order->order_code) }}" class="section-link">Lihat pesanan →</a>
        </div>

        <div class="alert-info">
            Total tagihan <strong>tidak diisi manual</strong>: setiap kali disimpan, total dihitung
            ulang dari harga satuan × jumlah. Nama produk juga tidak bisa diganti di sini karena
            itu salinan saat pesanan dibuat — kalau produknya yang keliru, hapus pesanan ini dan
            minta pembeli memesan ulang.
        </div>

        <form action="{{ route('order-update', $order->order_code) }}" method="post" class="card form-card">
            @csrf
            @method('put')

            <p class="detail-heading">Isi pesanan</p>

            <div class="field">
                <label for="product_name_snapshot" class="label">Produk</label>
                <input type="text" id="product_name_snapshot" class="input" disabled
                       value="{{ $order->product_name_snapshot }}">
                <p class="hint">
                    Salinan tetap — tidak bisa disunting.
                    @if ($order->product)
                        Produk aslinya: <a href="{{ route('product-show', $order->product) }}" class="paper-link">lihat di katalog</a>.
                    @else
                        Produk aslinya sudah tidak ada di katalog.
                    @endif
                </p>
            </div>

            <div class="field">
                <label for="quantity" class="label">Jumlah</label>
                <input type="number" id="quantity" name="quantity" class="input" required
                       min="1" max="{{ Order::MAX_QUANTITY }}" step="1"
                       value="{{ old('quantity', $order->quantity) }}">
                @error('quantity')
                    <p class="field-error">{{ $message }}</p>
                @enderror
                <p class="hint">1-{{ Order::MAX_QUANTITY }} pcs per pesanan, sama seperti aturan checkout.</p>
            </div>

            <div class="field">
                <label for="unit_price_snapshot" class="label">Harga Satuan (Rupiah)</label>
                <input type="number" id="unit_price_snapshot" name="unit_price_snapshot" class="input" required
                       min="1000" max="100000000" step="1"
                       value="{{ old('unit_price_snapshot', $order->unit_price_snapshot) }}">
                @error('unit_price_snapshot')
                    <p class="field-error">{{ $message }}</p>
                @enderror
                <p class="hint">
                    Angka penuh tanpa titik: <code>45000</code> = Rp45.000. Harga produk di katalog
                    sekarang {{ $order->product?->formatted_price ?? 'tidak tersedia' }} — salinan di
                    pesanan ini boleh berbeda (misalnya setelah nego).
                </p>
            </div>

            <p class="detail-heading">Data pembeli</p>

            <div class="field">
                <label for="buyer_name_snapshot" class="label">Nama Pemesan</label>
                <input type="text" id="buyer_name_snapshot" name="buyer_name_snapshot" class="input" required
                       minlength="2" maxlength="80"
                       value="{{ old('buyer_name_snapshot', $order->buyer_name_snapshot) }}">
                @error('buyer_name_snapshot')
                    <p class="field-error">{{ $message }}</p>
                @enderror
                <p class="hint">Nama inilah yang dipendekkan jadi testimoni kalau pesanan selesai.</p>
            </div>

            <div class="field">
                <label for="buyer_whatsapp" class="label">Nomor WhatsApp</label>
                <input type="text" id="buyer_whatsapp" name="buyer_whatsapp" class="input" required
                       maxlength="20" placeholder="081234567890"
                       value="{{ old('buyer_whatsapp', $order->buyer_whatsapp_snapshot) }}">
                @error('buyer_whatsapp')
                    <p class="field-error">{{ $message }}</p>
                @enderror
                <p class="hint">Otomatis dirapikan ke format <code>62…</code> saat disimpan.</p>
            </div>

            <div class="field">
                <label for="buyer_email_snapshot" class="label">Email (opsional)</label>
                <input type="email" id="buyer_email_snapshot" name="buyer_email_snapshot" class="input"
                       maxlength="255" placeholder="pembeli@contoh.id"
                       value="{{ old('buyer_email_snapshot', $order->buyer_email_snapshot) }}">
                @error('buyer_email_snapshot')
                    <p class="field-error">{{ $message }}</p>
                @enderror
            </div>

            <p class="detail-heading">Status pembayaran</p>

            <div class="field">
                <label for="payment_status" class="label">Pembayaran</label>
                <select id="payment_status" name="payment_status" class="input">
                    @foreach (Order::PAYMENT_STATUSES as $pilihan)
                        <option value="{{ $pilihan }}"
                                @selected(old('payment_status', $order->payment_status) === $pilihan)>
                            {{ Order::PAYMENT_LABELS[$pilihan] }}
                        </option>
                    @endforeach
                </select>
                @error('payment_status')
                    <p class="field-error">{{ $message }}</p>
                @enderror
                <p class="hint">
                    Menandai <strong>Lunas</strong> akan mengisi tanggal bayar
                    @if ($order->paid_at)
                        (sekarang {{ $order->paid_at->translatedFormat('j F Y, H:i') }}).
                    @else
                        (sekarang masih kosong).
                    @endif
                    Alur status pesanan (<em>{{ $order->status_label }}</em>) tetap diubah lewat
                    halaman pesanan, bukan di sini.
                    @if ($sudahNaik)
                        Pesanan ini sudah berstatus <strong>{{ $order->status_label }}</strong>,
                        jadi pembayarannya tidak bisa dikembalikan jadi belum lunas.
                    @endif
                </p>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-primary">Simpan Perubahan</button>
                <a href="{{ route('order-show', $order->order_code) }}" class="btn-secondary">Batal</a>
            </div>
        </form>

        {{-- Zona bahaya: dipisah dari form suntingan supaya tidak kepencet. --}}
        <div class="card order-panel">
            <p class="detail-heading">Hapus pesanan ini</p>
            <p class="hint">
                Menghapus <code>{{ $order->order_code }}</code> tidak menghapus produknya.
                Sebaliknya, ini justru membuka kunci produk yang tadinya tidak bisa dihapus
                karena sudah pernah dipesan.
                @if ($order->order_status === Order::STATUS_DONE)
                    Pesanan ini sudah <strong>selesai</strong>, jadi testimoninya ikut hilang
                    dari halaman testimoni.
                @endif
                Tindakan ini tidak bisa dibatalkan.
            </p>
            <form action="{{ route('order-destroy', $order->order_code) }}" method="post" class="order-action-form">
                @csrf
                @method('delete')
                <button type="submit" class="btn-danger"
                        onclick="return confirm('Hapus pesanan {{ $order->order_code }}? Tindakan ini tidak bisa dibatalkan.')">
                    Hapus pesanan
                </button>
                <a href="{{ route('order-list') }}" class="btn-secondary">← Semua pesanan</a>
            </form>
        </div>
    </div>
</x-layouts.app>
