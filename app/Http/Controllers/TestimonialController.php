<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\View\View;

/**
 * Testimoni otomatis — halaman publik /testimoni.
 *
 * Sumber datanya pesanan yang sudah DONE, sama seperti Anubis
 * (src/lib/testimonials.ts). Maksimum 20 pesanan terbaru.
 *
 * Privasi: halaman ini bisa dibaca siapa pun, termasuk tamu. Karena itu query
 * HANYA memilih kolom non-sensitif — nama pembeli (dipendekkan), nama produk,
 * jumlah, dan waktu selesai. Kode pesanan, nomor WhatsApp, email, dan nominal
 * tidak pernah diambil dari database, jadi bukan sekadar disembunyikan di view.
 */
class TestimonialController extends Controller
{
    /**
     * Jumlah testimoni terbanyak yang ditampilkan (TESTIMONIAL_LIMIT di Anubis).
     */
    public const LIMIT = 20;

    public function index(): View
    {
        $items = Order::query()
            ->done()
            ->latest('updated_at')
            ->latest('id')
            ->limit(self::LIMIT)
            ->get([
                'product_name_snapshot',
                'buyer_name_snapshot',
                'quantity',
                'updated_at',
            ]);

        return view('testimoni', [
            'items' => $items,
            'total' => Order::query()->done()->count(),
            'limit' => self::LIMIT,
        ]);
    }
}
