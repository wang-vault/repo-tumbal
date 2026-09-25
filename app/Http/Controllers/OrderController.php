<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Halaman pesanan (publik, kuncinya kode pesanan) + daftar kelola penjual.
 *
 * Alur manual yang dipakai:
 *   1. pembeli membuat pesanan            -> order_status PENDING
 *   2. pembeli transfer di luar aplikasi, lalu menekan "Saya sudah transfer"
 *                                           -> manual_claim_at terisi
 *   3. penjual memeriksa mutasi rekening:
 *        setuju -> payment_status PAID, paid_at, order_status PAID
 *        tolak  -> manual_review_status REJECTED, klaim dibersihkan
 *   4. penjual menaikkan status            -> PROCESSING -> DONE
 */
class OrderController extends Controller
{
    /**
     * Daftar kelola pesanan — khusus penjual yang sudah masuk.
     */
    public function index(Request $request): View
    {
        $status = strtoupper(trim((string) $request->query('status', '')));

        $orders = Order::query()
            ->with('product')
            ->status($status === '' ? null : $status)
            ->latest()
            ->latest('id')
            ->paginate(12)
            ->withQueryString();

        // Angka di tombol saring status, biar penjual tahu antreannya berapa.
        $counts = Order::query()
            ->selectRaw('order_status, count(*) as total')
            ->groupBy('order_status')
            ->pluck('total', 'order_status');

        return view('orders.index', [
            'orders' => $orders,
            'status' => in_array($status, array_keys(Order::TRANSITIONS), true) ? $status : '',
            'counts' => $counts,
            'total' => Order::count(),
        ]);
    }

    /**
     * Halaman pesanan — publik. Rute mengikat {order:order_code}, jadi yang
     * dipakai kode pesanan, bukan id: tidak bisa ditebak berurutan.
     */
    public function show(Order $order): View
    {
        $order->loadMissing('product');

        return view('orders.show', compact('order'));
    }

    /**
     * Pembeli menekan "Saya sudah transfer". Status belum berubah — penjual
     * tetap harus memverifikasi mutasinya lebih dulu.
     */
    public function claim(Request $request, Order $order): RedirectResponse
    {
        $back = redirect()->route('order-show', $order->order_code);

        if ($order->order_status !== Order::STATUS_PENDING) {
            return $back->with('error', 'Pesanan ini sudah tidak menunggu pembayaran.');
        }

        if ($order->isAwaitingReview()) {
            return $back->with('error', 'Klaim pembayaranmu sedang diperiksa penjual. Tunggu kabarnya, ya.');
        }

        $data = $request->validate([
            'manual_claim_note' => ['nullable', 'string', 'max:500'],
            'manual_claim_reference' => ['nullable', 'string', 'max:60'],
        ], [
            'manual_claim_note.max' => 'Catatan maksimal 500 karakter.',
            'manual_claim_reference.max' => 'Nomor referensi maksimal 60 karakter.',
        ], [
            'manual_claim_note' => 'catatan',
            'manual_claim_reference' => 'nomor referensi',
        ]);

        $order->update([
            'manual_claim_at' => now(),
            'manual_claim_note' => $data['manual_claim_note'] ?? '',
            'manual_claim_reference' => $data['manual_claim_reference'] ?? '',
            // Keputusan penjual yang lama dibatalkan: klaim baru menunggu review ulang.
            'manual_reviewed_at' => null,
            'manual_review_status' => null,
            'manual_review_note' => null,
        ]);

        return $back->with('success', 'Terima kasih. Klaim pembayaranmu sudah diteruskan ke penjual untuk dicek.');
    }

    /**
     * Penjual menaikkan status pesanan: PENDING -> PAID -> PROCESSING -> DONE.
     */
    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        $back = redirect()->route('order-show', $order->order_code);

        $data = $request->validate([
            'order_status' => ['required', 'string', 'max:20'],
            'manual_review_note' => ['nullable', 'string', 'max:500'],
        ], [], [
            'order_status' => 'status',
            'manual_review_note' => 'catatan penjual',
        ]);

        $target = strtoupper($data['order_status']);

        if (! $order->canTransitionTo($target)) {
            return $back->with('error', sprintf(
                'Status tidak bisa diubah dari "%s" menjadi "%s".',
                $order->status_label,
                Order::LABELS[$target] ?? $target
            ));
        }

        $order->order_status = $target;

        // Pembayaran dianggap lunas begitu pesanan naik ke PAID.
        if ($target === Order::STATUS_PAID) {
            $order->payment_status = 'PAID';
            $order->paid_at ??= now();

            if ($order->isClaimed()) {
                $order->manual_reviewed_at = now();
                $order->manual_review_status = 'APPROVED';
                $order->manual_review_note = $data['manual_review_note'] ?? '';
            }
        }

        $order->save();

        return $back->with('success', 'Pesanan '.$order->order_code.' sekarang berstatus '.$order->status_label.'.');
    }

    /**
     * Penjual menolak klaim pembeli (misalnya mutasinya tidak ada). Klaim
     * dibersihkan supaya pembeli bisa mengirim bukti yang benar.
     */
    public function rejectClaim(Request $request, Order $order): RedirectResponse
    {
        $back = redirect()->route('order-show', $order->order_code);

        if (! $order->isAwaitingReview()) {
            return $back->with('error', 'Tidak ada klaim pembayaran yang menunggu diperiksa.');
        }

        $data = $request->validate([
            'manual_review_note' => ['nullable', 'string', 'max:500'],
        ], [], ['manual_review_note' => 'alasan penolakan']);

        $order->update([
            'manual_reviewed_at' => now(),
            'manual_review_status' => 'REJECTED',
            'manual_review_note' => $data['manual_review_note'] ?? 'Transfer belum kami temukan di mutasi rekening.',
            'manual_claim_at' => null,
            'manual_claim_note' => null,
            'manual_claim_reference' => null,
        ]);

        return $back->with('error', 'Klaim pembayaran ditolak. Pembeli bisa mengirim ulang buktinya.');
    }
}
