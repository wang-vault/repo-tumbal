<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Enam produk pilihan (supaya tampilan beranda selalu sama) + empat produk
     * acak dari factory supaya katalog dan pencarian punya cukup baris.
     */
    public function run(): void
    {
        $produkPilihan = [
            [
                'name' => 'Kopi Tubruk Nusantara 200 g',
                'description' => 'Biji kopi robusta pilihan dari Temanggung, disangrai medium-dark. Digiling kasar khusus untuk kopi tubruk — seduh langsung di gelas, tunggu ampasnya turun.',
                'price' => 45000,
            ],
            [
                'name' => 'Kaos Kabar Pagi (Katun Combed 24s)',
                'description' => 'Kaos sablon tipografi "Kabar Pagi" dengan tinta plastisol. Bahan katun combed 24s, jahitan rantai, tersedia ukuran S sampai XXL.',
                'price' => 89000,
            ],
            [
                'name' => 'Tote Bag Kanvas Edisi Koran',
                'description' => 'Tas kanvas 12 oz motif halaman depan koran. Muat laptop 14 inci, ada saku dalam, jahitan rangkap di bagian pegangan.',
                'price' => 65000,
            ],
            [
                'name' => 'Mug Keramik Ink & Paper 350 ml',
                'description' => 'Mug keramik putih tulang dengan garis tinta hitam. Aman untuk microwave dan dishwasher, dikemas memakai dus daur ulang.',
                'price' => 55000,
            ],
            [
                'name' => 'Notebook Edisi Harian A5',
                'description' => 'Buku catatan 120 halaman kertas 80 gsm, sampul keras warna kertas koran, halaman pembuka berisi kolom tanggal dan cuaca.',
                'price' => 38000,
            ],
            [
                'name' => 'Stiker Pack Anubis (Isi 8)',
                'description' => 'Delapan stiker vinyl tahan air ukuran 5-9 cm: monogram Anubis, ikon WhatsApp, tanda "LUNAS", dan kutipan edisi harian.',
                'price' => 15000,
            ],
        ];

        // Produk acak dibuat lebih dulu supaya enam produk pilihan di bawah
        // menjadi yang "terbaru" dan pasti tampil di beranda.
        Product::factory()->count(4)->create();

        foreach ($produkPilihan as $produk) {
            Product::create($produk + ['is_active' => true]);
        }
    }
}
