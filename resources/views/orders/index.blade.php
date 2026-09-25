@php
    use App\Models\Order;

    $badge = [
        Order::STATUS_PENDING => 'badge-wait',
        Order::STATUS_PAID => 'badge-paid',
        Order::STATUS_PROCESSING => 'badge-work',
        Order::STATUS_DONE => 'badge-done',
    ];
@endphp

<x-layouts.app title="Kelola Pesanan">
    <div class="container-x stack">

        <div class="catalog-intro">
            <p class="section-kicker">Pesanan · Panel penjual</p>
            <h1 class="catalog-title">Kelola Pesanan</h1>
            <p class="catalog-deck">
                Semua pesanan yang masuk, terbaru lebih dulu. Pembayaran diverifikasi manual:
                periksa mutasi rekening dulu, baru naikkan statusnya.
            </p>

            {{-- Dua saringan bisa dipakai bersamaan: alur status dan status bayar.
                 Jumlahnya diambil dari query group, bukan dari halaman aktif. --}}
            <div class="filter-row">
                <span class="filter-label">Status</span>
                <a href="{{ route('order-list', ['payment' => $payment ?: null]) }}"
                   class="filter-chip {{ $status === '' ? 'is-active' : '' }}">
                    Semua <span class="filter-count">{{ $total }}</span>
                </a>
                @foreach (Order::FLOW as $tahap)
                    <a href="{{ route('order-list', ['status' => $tahap, 'payment' => $payment ?: null]) }}"
                       class="filter-chip {{ $status === $tahap ? 'is-active' : '' }}">
                        {{ Order::LABELS[$tahap] }}
                        <span class="filter-count">{{ $counts[$tahap] ?? 0 }}</span>
                    </a>
                @endforeach
            </div>

            <div class="filter-row">
                <span class="filter-label">Pembayaran</span>
                <a href="{{ route('order-list', ['status' => $status ?: null]) }}"
                   class="filter-chip {{ $payment === '' ? 'is-active' : '' }}">
                    Semua <span class="filter-count">{{ $total }}</span>
                </a>
                @foreach (Order::PAYMENT_STATUSES as $bayar)
                    <a href="{{ route('order-list', ['status' => $status ?: null, 'payment' => $bayar]) }}"
                       class="filter-chip {{ $payment === $bayar ? 'is-active' : '' }}">
                        {{ Order::PAYMENT_LABELS[$bayar] }}
                        <span class="filter-count">{{ $paymentCounts[$bayar] ?? 0 }}</span>
                    </a>
                @endforeach
            </div>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Kode</th>
                        <th>Produk</th>
                        <th>Pembeli</th>
                        <th>Jumlah</th>
                        <th>Total</th>
                        <th>Bayar</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($orders as $order)
                        <tr>
                            <td>{{ $orders->firstItem() + $loop->index }}</td>
                            <td><code class="order-code-cell">{{ $order->order_code }}</code></td>
                            <td>{{ $order->product_name_snapshot }}</td>
                            <td>
                                {{ $order->buyer_name_snapshot }}
                                <span class="cell-sub">{{ $order->buyer_whatsapp_snapshot }}</span>
                            </td>
                            <td>{{ $order->quantity }} pcs</td>
                            <td class="col-price">{{ $order->formatted_total }}</td>
                            <td>
                                <span class="badge {{ $order->isPaid() ? 'badge-paid' : 'badge-wait' }}">
                                    {{ $order->payment_label }}
                                </span>
                            </td>
                            <td>
                                <span class="badge {{ $badge[$order->order_status] ?? 'badge-wait' }}">
                                    {{ $order->status_label }}
                                </span>
                                @if ($order->isAwaitingReview())
                                    <span class="badge badge-off">klaim baru</span>
                                @endif
                            </td>
                            <td>
                                <div class="row-actions">
                                    <a href="{{ route('order-show', $order->order_code) }}">Buka</a>
                                    <span class="sep">|</span>
                                    <a href="{{ route('order-edit', $order->order_code) }}">Ubah</a>
                                    <span class="sep">|</span>
                                    <form action="{{ route('order-destroy', $order->order_code) }}" method="post" class="inline-form">
                                        @csrf
                                        @method('delete')
                                        {{-- @js() menghasilkan string JS berkutip tunggal, jadi atributnya berkutip ganda. --}}
                                        <button type="submit" class="link-danger"
                                                onclick="return confirm(@js("Hapus pesanan {$order->order_code}?"))">Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="table-empty">
                                @if ($status !== '' || $payment !== '')
                                    Tidak ada pesanan dengan saringan
                                    {{ $status !== '' ? 'status "'.(Order::LABELS[$status] ?? $status).'"' : '' }}
                                    {{ $status !== '' && $payment !== '' ? 'dan' : '' }}
                                    {{ $payment !== '' ? 'pembayaran "'.(Order::PAYMENT_LABELS[$payment] ?? $payment).'"' : '' }}.
                                    <a href="{{ route('order-list') }}">Lihat semua pesanan</a>.
                                @else
                                    Belum ada pesanan yang masuk.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $orders->links() }}

        <div class="form-actions">
            <a href="{{ route('product-list') }}" class="btn-secondary">← Katalog produk</a>
            <a href="{{ route('home') }}" class="btn-secondary">Beranda</a>
        </div>
    </div>
</x-layouts.app>
