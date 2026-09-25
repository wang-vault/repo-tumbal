<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Membuat pesanan baru.
 *
 * Pembeli TIDAK perlu masuk: kode pesanan (ORD-YYYYMMDD-XXXXXX) yang menjadi
 * tanda terima sekaligus kunci untuk membuka halaman pemesannya. Di aplikasi
 * Anubis asli pesanan ditautkan ke akun pembeli; di port ini tidak.
 */
class CheckoutController extends Controller
{
    /**
     * Form pesan. Produk nonaktif tidak bisa dipesan.
     */
    public function create(Product $product): View
    {
        $this->ensureIsOrderable($product);

        return view('checkout', compact('product'));
    }

    public function store(Request $request, Product $product): RedirectResponse
    {
        $this->ensureIsOrderable($product);

        // Nomor dinormalkan lebih dulu (0812… -> 62812…) supaya aturan regex-nya
        // memeriksa bentuk akhirnya, meniru normalizeWhatsapp() milik Anubis.
        $request->merge([
            'buyer_whatsapp' => Order::normalizeWhatsapp($request->string('buyer_whatsapp')->toString()),
        ]);

        $data = $request->validate([
            'buyer_name' => ['required', 'string', 'min:2', 'max:80'],
            'buyer_whatsapp' => ['required', 'string', 'max:20', 'regex:/^62[2-8][0-9]{7,12}$/'],
            'buyer_email' => ['nullable', 'email', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1', 'max:'.Order::MAX_QUANTITY],
        ], [
            'buyer_name.required' => 'Nama wajib diisi supaya penjual tahu pesanan ini milik siapa.',
            'buyer_name.min' => 'Nama minimal 2 karakter.',
            'buyer_whatsapp.required' => 'Nomor WhatsApp wajib diisi.',
            'buyer_whatsapp.regex' => 'Nomor WhatsApp tidak valid. Gunakan nomor Indonesia, contoh 081234567890.',
            'buyer_email.email' => 'Format email tidak valid.',
            'quantity.required' => 'Jumlah wajib diisi.',
            'quantity.integer' => 'Jumlah harus bilangan bulat.',
            'quantity.min' => 'Jumlah minimal 1.',
            'quantity.max' => 'Maksimal '.Order::MAX_QUANTITY.' pcs per pesanan.',
        ], [
            'buyer_name' => 'nama',
            'buyer_whatsapp' => 'nomor WhatsApp',
            'buyer_email' => 'email',
            'quantity' => 'jumlah',
        ]);

        $order = Order::create([
            'order_code' => Order::generateUniqueCode(),
            'product_id' => $product->id,
            // Snapshot: harga & nama dibekukan di sini, perubahan produk di
            // kemudian hari tidak mengubah pesanan yang sudah dibuat.
            'product_name_snapshot' => $product->name,
            'unit_price_snapshot' => $product->price,
            'quantity' => $data['quantity'],
            'total_amount' => $product->price * $data['quantity'],
            'payment_method' => 'MANUAL',
            'payment_status' => 'PENDING',
            'order_status' => Order::STATUS_PENDING,
            'buyer_name_snapshot' => $data['buyer_name'],
            'buyer_whatsapp_snapshot' => $data['buyer_whatsapp'],
            'buyer_email_snapshot' => $data['buyer_email'] ?? null,
        ]);

        return redirect()
            ->route('order-show', $order->order_code)
            ->with('success', 'Pesanan dibuat. Simpan kode '.$order->order_code.' untuk membuka halaman ini lagi.');
    }

    /**
     * Produk yang tidak dijual tidak boleh dipesan — diperlakukan seperti tidak ada.
     */
    private function ensureIsOrderable(Product $product): void
    {
        abort_unless($product->is_active, 404, 'Produk ini sedang tidak dijual.');
    }
}
