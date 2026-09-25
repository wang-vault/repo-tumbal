<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Halaman pesanan (publik, kuncinya kode pesanan) + kelola pesanan penjual.
 *
 * Sisi penjual lengkap selain baca: ubah (data pembeli, jumlah, harga, status
 * pembayaran) dan hapus. Membuat pesanan tetap lewat checkout, bukan di sini.
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
     *
     * Dua saringan bisa dipakai bersamaan: ?status= (alur pesanan) dan
     * ?payment= (status pembayaran), jadi penjual bisa mencari misalnya
     * "pesanan yang sudah lunas tapi belum diproses".
     */
    public function index(Request $request): View
    {
        $status = strtoupper(trim((string) $request->query('status', '')));
        $payment = strtoupper(trim((string) $request->query('payment', '')));

        $status = in_array($status, array_keys(Order::TRANSITIONS), true) ? $status : '';
        $payment = in_array($payment, Order::PAYMENT_STATUSES, true) ? $payment : '';

        $orders = Order::query()
            ->with('product')
            ->status($status)
            ->payment($payment)
            ->latest()
            ->latest('id')
            ->paginate(12)
            ->withQueryString();

        // Angka di tombol saring, biar penjual tahu antreannya berapa. Sengaja
        // dihitung dari seluruh pesanan (tanpa saringan) supaya tombol lain
        // tetap menampilkan jumlah yang masuk akal.
        $counts = Order::query()
            ->selectRaw('order_status, count(*) as total')
            ->groupBy('order_status')
            ->pluck('total', 'order_status');

        $paymentCounts = Order::query()
            ->selectRaw('payment_status, count(*) as total')
            ->groupBy('payment_status')
            ->pluck('total', 'payment_status');

        return view('orders.index', [
            'orders' => $orders,
            'status' => $status,
            'payment' => $payment,
            'counts' => $counts,
            'paymentCounts' => $paymentCounts,
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

    /**
     * Form ubah pesanan — khusus penjual. Dipakai untuk memperbaiki salah tulis
     * data pembeli, menyesuaikan jumlah/harga setelah negosiasi, atau menandai
     * pembayaran sudah lunas tanpa mengubah alur status.
     */
    public function edit(Order $order): View
    {
        $order->loadMissing('product');

        return view('orders.edit', compact('order'));
    }

    /**
     * Simpan perubahan pesanan. Total tidak diterima dari form: selalu dihitung
     * ulang oleh model (harga satuan x jumlah) supaya tidak bisa meleset.
     */
    public function update(Request $request, Order $order): RedirectResponse
    {
        $back = redirect()->route('order-show', $order->order_code);

        // Nomor dinormalkan lebih dulu (0812… -> 62812…), sama seperti checkout.
        $request->merge([
            'buyer_whatsapp' => Order::normalizeWhatsapp($request->string('buyer_whatsapp')->toString()),
        ]);

        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:'.Order::MAX_QUANTITY],
            'unit_price_snapshot' => ['required', 'integer', 'min:1000', 'max:100000000'],
            'buyer_name_snapshot' => ['required', 'string', 'min:2', 'max:80'],
            'buyer_whatsapp' => ['required', 'string', 'max:20', 'regex:/^62[2-8][0-9]{7,12}$/'],
            'buyer_email_snapshot' => ['nullable', 'email', 'max:255'],
            'payment_status' => ['required', 'string', Rule::in(Order::PAYMENT_STATUSES)],
        ], [
            'quantity.min' => 'Jumlah minimal 1.',
            'quantity.max' => 'Maksimal '.Order::MAX_QUANTITY.' pcs per pesanan.',
            'unit_price_snapshot.min' => 'Harga satuan minimal Rp1.000.',
            'unit_price_snapshot.max' => 'Harga satuan maksimal Rp100.000.000.',
            'buyer_name_snapshot.required' => 'Nama pemesan wajib diisi.',
            'buyer_name_snapshot.min' => 'Nama minimal 2 karakter.',
            'buyer_whatsapp.required' => 'Nomor WhatsApp wajib diisi.',
            'buyer_whatsapp.regex' => 'Nomor WhatsApp tidak valid. Gunakan nomor Indonesia, contoh 081234567890.',
            'buyer_email_snapshot.email' => 'Format email tidak valid.',
            'payment_status.required' => 'Status pembayaran wajib dipilih.',
            'payment_status.in' => 'Status pembayaran tidak dikenal.',
        ], [
            'quantity' => 'jumlah',
            'unit_price_snapshot' => 'harga satuan',
            'buyer_name_snapshot' => 'nama pemesan',
            'buyer_whatsapp' => 'nomor WhatsApp',
            'buyer_email_snapshot' => 'email',
            'payment_status' => 'status pembayaran',
        ]);

        $payment = strtoupper($data['payment_status']);

        // Pesanan yang sudah naik status berarti uangnya sudah diterima, jadi
        // status bayarnya tidak boleh ditarik kembali jadi "belum lunas".
        if ($payment === Order::PAYMENT_PENDING && $order->order_status !== Order::STATUS_PENDING) {
            return $back->with('error', sprintf(
                'Pesanan berstatus "%s" tidak bisa dikembalikan jadi belum lunas.',
                $order->status_label
            ));
        }

        $order->quantity = (int) $data['quantity'];
        $order->unit_price_snapshot = (int) $data['unit_price_snapshot'];
        $order->buyer_name_snapshot = $data['buyer_name_snapshot'];
        $order->buyer_whatsapp_snapshot = $data['buyer_whatsapp'];
        $order->buyer_email_snapshot = $data['buyer_email_snapshot'] ?? null;
        $order->payment_status = $payment;
        $order->paid_at = $payment === Order::PAYMENT_PAID ? ($order->paid_at ?? now()) : null;
        $order->save();

        return $back->with('success', sprintf(
            'Pesanan %s diperbarui. Totalnya sekarang %s (%s).',
            $order->order_code,
            $order->formatted_total,
            $order->payment_label
        ));
    }

    /**
     * Hapus pesanan — khusus penjual, untuk pesanan uji atau duplikat.
     *
     * Produknya tidak ikut terhapus. Justru sebaliknya: menghapus pesanan bisa
     * membuka kunci produk yang tadinya tidak bisa dihapus karena sudah dipesan
     * (FK product_id memakai ON DELETE RESTRICT).
     */
    public function destroy(Order $order): RedirectResponse
    {
        $code = $order->order_code;
        $wasDone = $order->order_status === Order::STATUS_DONE;
        $product = $order->product_name_snapshot;

        $order->delete();

        $message = 'Pesanan '.$code.' ('.$product.') dihapus.';
        if ($wasDone) {
            $message .= ' Testimoninya ikut hilang dari halaman testimoni.';
        }

        return redirect()->route('order-list')->with('success', $message);
    }
}
