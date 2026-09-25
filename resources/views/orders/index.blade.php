<x-layouts.app title="Kelola Pesanan">
    <div class="container-x stack">

        <div class="catalog-intro">
            <p class="section-kicker">Pesanan · Panel penjual</p>
            <h1 class="catalog-title">Kelola Pesanan</h1>
            <p class="catalog-deck">
                Semua pesanan yang masuk, terbaru lebih dulu. Pembayaran diverifikasi manual:
                periksa mutasi rekening dulu, baru naikkan statusnya.
            </p>

            {{-- Saring berdasarkan status; jumlahnya diambil dari satu query group. --}}
            <div class="filter-row">
                <a href="{{ route('order-list') }}"
                   class="filter-chip {{ $status === '' ? 'is-active' : '' }}">
                    Semua <span class="filter-count">{{ $total }}</span>
                </a>
                @foreach (\App\Models\Order::FLOW as $tahap)
                    <a href="{{ route('order-list', ['status' => $tahap]) }}"
                       class="filter-chip {{ $status === $tahap ? 'is-active' : '' }}">
                        {{ \App\Models\Order::LABELS[$tahap] }}
                        <span class="filter-count">{{ $counts[$tahap] ?? 0 }}</span>
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
                                <span class="badge {{ [
                                    'PENDING' => 'badge-wait',
                                    'PAID' => 'badge-paid',
                                    'PROCESSING' => 'badge-work',
                                    'DONE' => 'badge-done',
                                ][$order->order_status] ?? 'badge-wait' }}">
                                    {{ $order->status_label }}
                                </span>
                                @if ($order->isAwaitingReview())
                                    <span class="badge badge-off">klaim baru</span>
                                @endif
                            </td>
                            <td>
                                <div class="row-actions">
                                    <a href="{{ route('order-show', $order->order_code) }}">Buka</a>
                                    @if ($order->product)
                                        <span class="sep">|</span>
                                        <a href="{{ route('product-show', $order->product) }}">Produk</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="table-empty">
                                @if ($status !== '')
                                    Belum ada pesanan berstatus {{ \App\Models\Order::LABELS[$status] ?? $status }}.
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
