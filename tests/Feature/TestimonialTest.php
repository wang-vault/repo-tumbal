<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Menjalankan: php artisan test --filter=TestimonialTest
 *
 * Halaman /testimoni bersifat PUBLIK, jadi yang diuji bukan cuma isinya tetapi
 * juga apa yang TIDAK boleh bocor ke tamu.
 */
class TestimonialTest extends TestCase
{
    use RefreshDatabase;

    private function product(): Product
    {
        return Product::factory()->create(['name' => 'Mug Keramik Ink & Paper', 'price' => 55000]);
    }

    public function test_testimoni_hanya_menampilkan_pesanan_selesai(): void
    {
        Order::factory()->done()->create(['buyer_name_snapshot' => 'Pembeli Selesai']);
        Order::factory()->create(['buyer_name_snapshot' => 'Pembeli Menunggu']);
        Order::factory()->paid()->create(['buyer_name_snapshot' => 'Pembeli Lunas']);
        Order::factory()->processing()->create(['buyer_name_snapshot' => 'Pembeli Diproses']);

        $this->get(route('testimoni'))
            ->assertOk()
            ->assertSee('Pembeli S.')          // nama sudah dipendekkan
            ->assertDontSee('Pembeli Menunggu')
            ->assertDontSee('Pembeli Lunas')
            ->assertDontSee('Pembeli Diproses');
    }

    public function test_nama_pembeli_dipendekkan_demi_privasi(): void
    {
        // Port dari maskBuyerName() milik Anubis.
        $this->assertSame('Budi S.', Order::maskBuyerName('Budi Santoso'));
        $this->assertSame('Budi A.', Order::maskBuyerName('Budi Santoso Andi'));
        $this->assertSame('R***y', Order::maskBuyerName('Rizky'));
        $this->assertSame('J***', Order::maskBuyerName('Jo'));
        $this->assertSame('Pembeli', Order::maskBuyerName(''));
        $this->assertSame('Pembeli', Order::maskBuyerName(null));
        $this->assertSame('Siti R.', Order::maskBuyerName('  Siti   Rahayu  '));
    }

    public function test_testimoni_tidak_membocorkan_data_pribadi(): void
    {
        $order = Order::factory()->done()->create([
            'buyer_name_snapshot' => 'Budi Santoso',
            'buyer_whatsapp_snapshot' => '6281234567890',
            'buyer_email_snapshot' => 'budi@rahasia.test',
            'unit_price_snapshot' => 45000,
            'quantity' => 3,
            'total_amount' => 135000,
        ]);

        $this->get(route('testimoni'))
            ->assertOk()
            ->assertSee('Budi S.')
            ->assertDontSee('Budi Santoso')
            ->assertDontSee($order->order_code)
            ->assertDontSee('6281234567890')
            ->assertDontSee('budi@rahasia.test')
            // Nominal tidak pernah dikirim ke view, jadi "Rp" tidak muncul sama sekali.
            ->assertDontSee('Rp');
    }

    public function test_testimoni_dibatasi_dua_puluh_pesanan_terbaru(): void
    {
        $product = $this->product();

        for ($i = 1; $i <= 25; $i++) {
            Order::factory()->done()->create([
                'product_id' => $product->id,
                'product_name_snapshot' => $product->name,
                'unit_price_snapshot' => $product->price,
                'total_amount' => $product->price,
                'buyer_name_snapshot' => 'Urut'.str_pad((string) $i, 2, '0', STR_PAD_LEFT).' Pembeli',
                'updated_at' => now()->subMinutes(30 - $i),
            ]);
        }

        $response = $this->get(route('testimoni'))->assertOk();

        $this->assertSame(20, substr_count($response->getContent(), 'testimoni-card'));
        $response->assertSee('Urut25 P.');      // paling baru ikut ditampilkan
        $response->assertDontSee('Urut01 P.');  // paling lama terpotong batas 20
    }

    public function test_halaman_testimoni_kosong_menawarkan_katalog(): void
    {
        Order::factory()->create();   // masih PENDING, bukan testimoni

        $this->get(route('testimoni'))
            ->assertOk()
            ->assertSee('Belum ada testimoni')
            ->assertSee(route('product-list'), false);
    }
}
