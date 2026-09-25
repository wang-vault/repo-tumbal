@php
    use App\Models\Order;

    // Posisi status sekarang di dalam alur, untuk menggambar linimasa.
    $posisi = array_search($order->order_status, Order::FLOW, true);

    $badge = [
        Order::STATUS_PENDING => 'badge-wait',
        Order::STATUS_PAID => 'badge-paid',
        Order::STATUS_PROCESSING => 'badge-work',
        Order::STATUS_DONE => 'badge-done',
    ][$order->order_status] ?? 'badge-wait';

    $labelAksi = [
        Order::STATUS_PAID => 'Tandai sudah dibayar',
        Order::STATUS_PROCESSING => 'Mulai diproses',
        Order::STATUS_DONE => 'Tandai selesai',
    ];
@endphp

<x-layouts.app title="Pesanan {{ $order->order_code }}">
    <div class="container-x stack">

        <div class="section-heading">
            <div>
                <p class="section-kicker">Pesanan · dibuat {{ $order->created_at->translatedFormat('j F Y, H:i') }}</p>
                <h1 class="section-title">{{ $order->order_code }}</h1>
            </div>
            @auth
                <a href="{{ route('order-list') }}" class="section-link">← Daftar pesanan</a>
            @else
                <a href="{{ route('product-list') }}" class="section-link">← Katalog produk</a>
            @endauth
        </div>

        {{-- Linimasa status: langkah yang sudah lewat ditandai, yang sekarang disorot. --}}
        <ol class="status-track" aria-label="Perjalanan pesanan">
            @foreach (Order::FLOW as $index => $tahap)
                <li class="status-step
                           {{ $index < $posisi ? 'status-step--done' : '' }}
                           {{ $index === $posisi ? 'status-step--current' : '' }}"
                    @if ($index === $posisi) aria-current="step" @endif>
                    <span class="status-dot" aria-hidden="true">{{ $index < $posisi ? '✓' : $index + 1 }}</span>
                    <span class="status-name">{{ Order::LABELS[$tahap] }}</span>
                </li>
            @endforeach
        </ol>

        <div class="detail-grid">

            {{-- ====================== Rincian pesanan ====================== --}}
            <div class="card order-detail">
                <p class="detail-heading">Rincian pesanan</p>

                <table class="data-table order-lines">
                    <thead>
                        <tr>
                            <th>Produk</th>
                            <th>Harga satuan</th>
                            <th>Jumlah</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                @if ($order->product)
                                    <a href="{{ route('product-show', $order->product) }}" class="paper-link">
                                        {{ $order->product_name_snapshot }}
                                    </a>
                                @else
                                    {{ $order->product_name_snapshot }}
                                @endif
                            </td>
                            <td class="col-price">{{ $order->formatted_unit_price }}</td>
                            <td>{{ $order->quantity }} pcs</td>
                            <td class="col-price">{{ $order->formatted_total }}</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="3">Total tagihan</th>
                            <td class="col-price order-total">{{ $order->formatted_total }}</td>
                        </tr>
                    </tfoot>
                </table>

                <p class="hint">
                    Nama dan harga di atas adalah salinan saat pesanan dibuat
                    ({{ $order->created_at->translatedFormat('j F Y') }}), jadi tidak berubah
                    walau produknya nanti disunting.
                </p>

                <dl class="detail-meta">
                    <div>
                        <dt>Pemesan</dt>
                        <dd>{{ $order->buyer_name_snapshot }}</dd>
                    </div>
                    <div>
                        <dt>WhatsApp</dt>
                        <dd>{{ $order->buyer_whatsapp_snapshot }}</dd>
                    </div>
                    @if ($order->buyer_email_snapshot)
                        <div>
                            <dt>Email</dt>
                            <dd>{{ $order->buyer_email_snapshot }}</dd>
                        </div>
                    @endif
                    <div>
                        <dt>Metode bayar</dt>
                        <dd>Transfer manual</dd>
                    </div>
                    <div>
                        <dt>Status bayar</dt>
                        <dd>
                            <span class="badge {{ $order->isPaid() ? 'badge-done' : 'badge-wait' }}">
                                {{ $order->payment_label }}
                            </span>
                            @if ($order->paid_at)
                                · {{ $order->paid_at->translatedFormat('j F Y, H:i') }}
                            @endif
                        </dd>
                    </div>
                </dl>

                {{-- Riwayat klaim & verifikasi --}}
                @if ($order->isClaimed() || $order->manual_review_status)
                    <div class="claim-history">
                        <p class="detail-heading">Riwayat pembayaran</p>

                        @if ($order->isClaimed())
                            <p class="claim-line">
                                <span class="badge badge-paid">Klaim pembeli</span>
                                {{ $order->manual_claim_at->translatedFormat('j F Y, H:i') }}
                                @if ($order->manual_claim_reference)
                                    · referensi <code>{{ $order->manual_claim_reference }}</code>
                                @endif
                            </p>
                            @if ($order->manual_claim_note)
                                <p class="claim-note">“{{ $order->manual_claim_note }}”</p>
                            @endif
                        @endif

                        @if ($order->manual_review_status === 'APPROVED')
                            <p class="claim-line">
                                <span class="badge badge-done">Disetujui penjual</span>
                                {{ $order->manual_reviewed_at?->translatedFormat('j F Y, H:i') }}
                            </p>
                            @if ($order->manual_review_note)
                                <p class="claim-note">“{{ $order->manual_review_note }}”</p>
                            @endif
                        @elseif ($order->manual_review_status === 'REJECTED')
                            <p class="claim-line">
                                <span class="badge badge-off">Klaim ditolak</span>
                                {{ $order->manual_reviewed_at?->translatedFormat('j F Y, H:i') }}
                            </p>
                            @if ($order->manual_review_note)
                                <p class="claim-note">“{{ $order->manual_review_note }}”</p>
                            @endif
                            <p class="hint">Kirim ulang bukti transfer lewat form di sebelah kalau sudah diperbaiki.</p>
                        @endif
                    </div>
                @endif
            </div>

            {{-- ====================== Tindakan ====================== --}}
            <div class="stack">

                @auth
                    {{-- Panel penjual: verifikasi klaim + naikkan status --}}
                    <div class="card order-panel">
                        <p class="detail-heading">Kelola pesanan</p>

                        @if ($order->isAwaitingReview())
                            <div class="alert-warn">
                                <strong>Pembeli mengaku sudah transfer</strong>
                                {{ $order->manual_claim_at->translatedFormat('j F Y, H:i') }}.
                                Cek mutasi rekening dulu sebelum menyetujui.
                            </div>

                            <form action="{{ route('order-claim-reject', $order->order_code) }}" method="post" class="order-action-form">
                                @csrf
                                <div class="field">
                                    <label for="reject_note" class="label">Alasan menolak (opsional)</label>
                                    <input type="text" id="reject_note" name="manual_review_note" class="input"
                                           maxlength="500" placeholder="Transfer belum terlihat di mutasi">
                                </div>
                                <button type="submit" class="btn-danger"
                                        onclick="return confirm('Tolak klaim pembayaran ini?')">Tolak klaim</button>
                            </form>
                        @endif

                        @forelse ($order->nextStatuses() as $next)
                            <form action="{{ route('order-status', $order->order_code) }}" method="post" class="order-action-form">
                                @csrf
                                <input type="hidden" name="order_status" value="{{ $next }}">

                                @if ($next === Order::STATUS_PAID)
                                    <div class="field">
                                        <label for="review_note" class="label">Catatan verifikasi (opsional)</label>
                                        <input type="text" id="review_note" name="manual_review_note" class="input"
                                               maxlength="500" placeholder="Mutasi Rp… diterima pukul …">
                                    </div>
                                @endif

                                <button type="submit" class="btn-primary">{{ $labelAksi[$next] ?? 'Ubah status' }}</button>
                            </form>
                        @empty
                            <p class="hint">Pesanan sudah selesai — tidak ada status berikutnya.</p>
                        @endforelse

                        <div class="form-actions">
                            <a href="{{ route('order-edit', $order->order_code) }}" class="btn-secondary">Ubah pesanan</a>
                            @if ($order->product)
                                <a href="{{ route('product-edit', $order->product) }}" class="btn-secondary">Ubah produknya</a>
                            @endif
                            <a href="{{ route('order-list') }}" class="btn-secondary">← Semua pesanan</a>
                            <form action="{{ route('order-destroy', $order->order_code) }}" method="post" class="inline-form">
                                @csrf
                                @method('delete')
                                <button type="submit" class="btn-danger"
                                        onclick="return confirm(@js("Hapus pesanan {$order->order_code}? Tindakan ini tidak bisa dibatalkan."))">
                                    Hapus pesanan
                                </button>
                            </form>
                        </div>
                    </div>
                @else
                    {{-- Panel pembeli: klaim sudah transfer --}}
                    <div class="card order-panel">
                        <p class="detail-heading">Sudah bayar?</p>

                        @if ($order->order_status === Order::STATUS_PENDING)
                            @if ($order->isAwaitingReview())
                                <div class="alert-info">
                                    Klaim pembayaranmu sedang diperiksa penjual. Halaman ini akan
                                    berubah begitu penjual memutuskannya — simpan kode
                                    <code>{{ $order->order_code }}</code> untuk membukanya lagi.
                                </div>
                            @else
                                <p class="hint">
                                    Transfer dulu lewat rekening/QRIS yang diberikan penjual di WhatsApp,
                                    lalu beri tahu di sini supaya penjual memeriksanya.
                                </p>

                                <form action="{{ route('order-claim', $order->order_code) }}" method="post" class="order-action-form">
                                    @csrf
                                    <div class="field">
                                        <label for="manual_claim_reference" class="label">Nomor referensi / ID transaksi (opsional)</label>
                                        <input type="text" id="manual_claim_reference" name="manual_claim_reference"
                                               class="input" maxlength="60" placeholder="contoh: TRX889912"
                                               value="{{ old('manual_claim_reference') }}">
                                        @error('manual_claim_reference')
                                            <p class="field-error">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div class="field">
                                        <label for="manual_claim_note" class="label">Catatan (opsional)</label>
                                        <textarea id="manual_claim_note" name="manual_claim_note" class="input" rows="3"
                                                  maxlength="500" placeholder="Nama pengirim, bank, dan jam transfer">{{ old('manual_claim_note') }}</textarea>
                                        @error('manual_claim_note')
                                            <p class="field-error">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <button type="submit" class="btn-primary">Saya sudah transfer</button>
                                </form>
                            @endif
                        @else
                            <div class="alert-info">
                                Pesanan ini berstatus <strong>{{ $order->status_label }}</strong>.
                                Tidak ada yang perlu kamu lakukan — tunggu kabar dari penjual.
                            </div>
                        @endif
                    </div>
                @endauth

                <div class="card order-panel">
                    <p class="detail-heading">Simpan kode ini</p>
                    <p class="order-code-display"><code>{{ $order->order_code }}</code></p>
                    <p class="hint">
                        Halaman ini terbuka tanpa login; kodenya yang menjadi kunci.
                        Siapa pun yang tahu kodenya bisa melihat rincian pesanan,
                        jadi jangan dibagikan sembarangan.
                    </p>
                </div>

            </div>
        </div>
    </div>
</x-layouts.app>
