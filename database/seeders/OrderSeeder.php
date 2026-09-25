<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    /**
     * Enam pesanan contoh yang menutupi seluruh alur status, supaya daftar kelola
     * penjual dan halaman /testimoni langsung ada isinya.
     *
     * Datanya ditulis tetap (bukan fake()) supaya tampilan testimoni selalu sama.
     * Nomor WhatsApp di bawah ini nomor karangan, bukan nomor sungguhan.
     */
    public function run(): void
    {
        // Enam produk pilihan dari ProductSeeder adalah yang paling baru.
        $products = Product::query()->latest('id')->limit(6)->get();

        if ($products->isEmpty()) {
            $this->command?->warn('OrderSeeder dilewati: belum ada produk. Jalankan ProductSeeder lebih dulu.');

            return;
        }

        $pesanan = [
            ['produk' => 0, 'nama' => 'Budi Santoso', 'wa' => '6281234567890', 'jumlah' => 2, 'status' => Order::STATUS_DONE, 'umur_hari' => 9],
            ['produk' => 1, 'nama' => 'Siti Rahayu', 'wa' => '6281298765432', 'jumlah' => 1, 'status' => Order::STATUS_DONE, 'umur_hari' => 6],
            ['produk' => 2, 'nama' => 'Rizky', 'wa' => '6285611223344', 'jumlah' => 3, 'status' => Order::STATUS_PROCESSING, 'umur_hari' => 3],
            ['produk' => 3, 'nama' => 'Andi Wijaya Kusuma', 'wa' => '6287755664433', 'jumlah' => 1, 'status' => Order::STATUS_PAID, 'umur_hari' => 2],
            ['produk' => 4, 'nama' => 'Maria Ulfa', 'wa' => '6281355779911', 'jumlah' => 2, 'status' => Order::STATUS_PENDING, 'umur_hari' => 1],
            ['produk' => 5, 'nama' => 'Yusuf Hamdani', 'wa' => '6282199887766', 'jumlah' => 1, 'status' => Order::STATUS_PENDING, 'umur_hari' => 0, 'klaim' => true],
        ];

        foreach ($pesanan as $item) {
            $product = $products[$item['produk'] % $products->count()];

            $pending = $item['status'] === Order::STATUS_PENDING;
            // Pesanan yang sudah dibayar pasti pernah diklaim pembeli; yang masih
            // menunggu hanya diklaim kalau memang ditandai 'klaim' => true.
            $claimed = ! $pending || ($item['klaim'] ?? false);

            $order = Order::create([
                'order_code' => Order::generateUniqueCode(now()->subDays($item['umur_hari'])),
                'product_id' => $product->id,
                'product_name_snapshot' => $product->name,
                'unit_price_snapshot' => $product->price,
                'quantity' => $item['jumlah'],
                'total_amount' => $product->price * $item['jumlah'],
                'payment_method' => 'MANUAL',
                'payment_status' => $pending ? 'PENDING' : 'PAID',
                'order_status' => $item['status'],
                'buyer_name_snapshot' => $item['nama'],
                'buyer_whatsapp_snapshot' => $item['wa'],
                'paid_at' => $pending ? null : now()->subDays($item['umur_hari'])->addHours(5),
                'manual_claim_at' => $claimed ? now()->subDays($item['umur_hari'])->addHours(4) : null,
                'manual_claim_note' => $claimed ? 'Sudah transfer sesuai nominal, mohon dicek.' : null,
                // Klaim yang belum diputuskan penjual: manual_review_* masih null.
                'manual_reviewed_at' => $pending ? null : now()->subDays($item['umur_hari'])->addHours(6),
                'manual_review_status' => $pending ? null : 'APPROVED',
            ]);

            // Mundurkan tanggal supaya urutannya terlihat alami di daftar maupun
            // di halaman testimoni (yang memakai updated_at sebagai waktu selesai).
            $order->timestamps = false;
            $order->created_at = now()->subDays($item['umur_hari']);
            $order->updated_at = now()->subDays($item['umur_hari'])->addHours(6);
            $order->save();
        }
    }
}
