<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Menjalankan: php artisan test --filter=OrderTest
 *
 * Membeli tidak butuh login; mengelola pesanan butuh penjual (actingAs).
 */
class OrderTest extends TestCase
{
    use RefreshDatabase;

    private function seller(): User
    {
        return User::factory()->create();
    }

    private function product(array $attributes = []): Product
    {
        return Product::factory()->create($attributes + [
            'name' => 'Kaos Kabar Pagi',
            'price' => 89000,
            'is_active' => true,
        ]);
    }

    /**
     * Data checkout yang sah.
     *
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'buyer_name' => 'Budi Santoso',
            'buyer_whatsapp' => '081234567890',
            'buyer_email' => '',
            'quantity' => 2,
        ];
    }

    public function test_pembeli_bisa_membuat_pesanan_tanpa_login(): void
    {
        $product = $this->product();

        $response = $this->post(route('order-store', $product), $this->payload());

        $this->assertDatabaseCount('orders', 1);

        $order = Order::firstOrFail();
        $response->assertRedirect(route('order-show', $order->order_code));
        $response->assertSessionHas('success');

        $this->assertSame($product->id, $order->product_id);
        $this->assertSame('Kaos Kabar Pagi', $order->product_name_snapshot);
        $this->assertSame(89000, $order->unit_price_snapshot);
        $this->assertSame(2, $order->quantity);
        $this->assertSame(178000, $order->total_amount);
        $this->assertSame(Order::STATUS_PENDING, $order->order_status);
        $this->assertSame('PENDING', $order->payment_status);
        $this->assertSame('MANUAL', $order->payment_method);
        $this->assertSame('Budi Santoso', $order->buyer_name_snapshot);
    }

    public function test_kode_pesanan_memakai_format_ord_tanggal_enam_karakter(): void
    {
        $this->post(route('order-store', $this->product()), $this->payload())->assertRedirect();

        $code = Order::firstOrFail()->order_code;

        $this->assertMatchesRegularExpression('/^ORD-\d{8}-[A-Z0-9]{6}$/', $code);

        // Alfabetnya membuang 0/O dan 1/I/L supaya tidak salah baca di chat.
        $this->assertDoesNotMatchRegularExpression('/[OI]/', substr($code, -6));
    }

    public function test_pembangkit_kode_memakai_zona_wib(): void
    {
        // 31 Desember 2026 20:00 UTC masih 1 Januari 2027 03:00 di WIB.
        $code = Order::generateCode(now('UTC')->setDate(2026, 12, 31)->setTime(20, 0));

        $this->assertStringStartsWith('ORD-20270101-', $code);
    }

    public function test_halaman_pesanan_terbuka_tanpa_login_lewat_kodenya(): void
    {
        $order = Order::factory()->create([
            'buyer_name_snapshot' => 'Siti Rahayu',
            'quantity' => 3,
            'unit_price_snapshot' => 45000,
            'total_amount' => 135000,
        ]);

        $this->get(route('order-show', $order->order_code))
            ->assertOk()
            ->assertSee($order->order_code)
            ->assertSee('Siti Rahayu')
            ->assertSee('Rp135.000')
            ->assertSee('Menunggu pembayaran');
    }

    public function test_kode_pesanan_yang_tidak_ada_mengembalikan_404(): void
    {
        $this->get('/orders/ORD-20260101-XXXXXX')
            ->assertNotFound()
            ->assertSee('Halaman tidak ditemukan');
    }

    public function test_snapshot_harga_tidak_berubah_saat_produk_disunting(): void
    {
        $product = $this->product(['price' => 50000]);

        $this->post(route('order-store', $product), $this->payload(['quantity' => 2]));

        // Penjual menaikkan harga sesudah pesanan masuk.
        $this->actingAs($this->seller())->put("/products/{$product->id}", [
            'name' => 'Kaos Kabar Pagi',
            'price' => 999000,
            'is_active' => '1',
        ])->assertRedirect();

        $order = Order::firstOrFail();
        $this->assertSame(50000, $order->unit_price_snapshot);
        $this->assertSame(100000, $order->total_amount);
        $this->assertSame(999000, $product->fresh()->price);
    }

    public function test_validasi_checkout_menolak_data_tidak_sah(): void
    {
        $product = $this->product();

        $this->post(route('order-store', $product), [
            'buyer_name' => 'B',
            'buyer_whatsapp' => '12345',
            'quantity' => 0,
        ])->assertSessionHasErrors(['buyer_name', 'buyer_whatsapp', 'quantity']);

        // Melebihi batas 20 pcs per pesanan (aturan checkout Anubis).
        $this->post(route('order-store', $product), $this->payload(['quantity' => 21]))
            ->assertSessionHasErrors('quantity');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_nomor_whatsapp_dinormalkan_ke_format_62(): void
    {
        $product = $this->product();

        foreach ([
            '0812-3456-7890' => '6281234567890',
            '+62 812 3456 7890' => '6281234567890',
            '81234567890' => '6281234567890',
        ] as $input => $expected) {
            $this->post(route('order-store', $product), $this->payload(['buyer_whatsapp' => $input]))
                ->assertRedirect();

            $this->assertSame($expected, Order::latest('id')->first()->buyer_whatsapp_snapshot);
        }
    }

    public function test_produk_nonaktif_tidak_bisa_dipesan(): void
    {
        $product = $this->product(['is_active' => false]);

        $this->get(route('checkout', $product))->assertNotFound();
        $this->post(route('order-store', $product), $this->payload())->assertNotFound();

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_pembeli_bisa_mengklaim_sudah_transfer(): void
    {
        $order = Order::factory()->create();

        $this->post(route('order-claim', $order->order_code), [
            'manual_claim_reference' => 'TRX889912',
            'manual_claim_note' => 'Transfer lewat m-banking pukul 10.00.',
        ])->assertRedirect(route('order-show', $order->order_code));

        $order->refresh();
        $this->assertNotNull($order->manual_claim_at);
        $this->assertSame('TRX889912', $order->manual_claim_reference);
        // Status belum naik: penjual tetap harus memverifikasi mutasinya.
        $this->assertSame(Order::STATUS_PENDING, $order->order_status);
        $this->assertTrue($order->isAwaitingReview());
    }

    public function test_klaim_ditolak_kalau_pesanan_sudah_dibayar(): void
    {
        $order = Order::factory()->paid()->create();

        $this->post(route('order-claim', $order->order_code), ['manual_claim_note' => 'sudah transfer'])
            ->assertRedirect(route('order-show', $order->order_code))
            ->assertSessionHas('error');

        $this->assertSame(Order::STATUS_PAID, $order->fresh()->order_status);
    }

    public function test_daftar_pesanan_hanya_untuk_penjual(): void
    {
        $order = Order::factory()->create();

        $this->get(route('order-list'))->assertRedirect(route('login'));

        $this->actingAs($this->seller())->get(route('order-list'))
            ->assertOk()
            ->assertSee($order->order_code)
            ->assertSee('Kelola Pesanan');
    }

    public function test_penjual_bisa_menaikkan_status_sampai_selesai(): void
    {
        $seller = $this->seller();
        $order = Order::factory()->claimed()->create();

        // PENDING -> PAID sekaligus menyetujui klaim pembeli.
        $this->actingAs($seller)
            ->post(route('order-status', $order->order_code), [
                'order_status' => Order::STATUS_PAID,
                'manual_review_note' => 'Mutasi Rp178.000 sudah masuk.',
            ])
            ->assertRedirect(route('order-show', $order->order_code));

        $order->refresh();
        $this->assertSame(Order::STATUS_PAID, $order->order_status);
        $this->assertSame('PAID', $order->payment_status);
        $this->assertNotNull($order->paid_at);
        $this->assertSame('APPROVED', $order->manual_review_status);
        $this->assertSame('Mutasi Rp178.000 sudah masuk.', $order->manual_review_note);

        // PAID -> PROCESSING -> DONE.
        $this->actingAs($seller)->post(route('order-status', $order->order_code), ['order_status' => Order::STATUS_PROCESSING]);
        $this->assertSame(Order::STATUS_PROCESSING, $order->fresh()->order_status);

        $this->actingAs($seller)->post(route('order-status', $order->order_code), ['order_status' => Order::STATUS_DONE]);
        $this->assertSame(Order::STATUS_DONE, $order->fresh()->order_status);
    }

    public function test_lompatan_status_ditolak(): void
    {
        $order = Order::factory()->create();

        $this->actingAs($this->seller())
            ->post(route('order-status', $order->order_code), ['order_status' => Order::STATUS_DONE])
            ->assertRedirect(route('order-show', $order->order_code))
            ->assertSessionHas('error');

        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->order_status);
        $this->assertNull($order->fresh()->paid_at);
    }

    public function test_penjual_bisa_menolak_klaim_pembeli(): void
    {
        $order = Order::factory()->claimed()->create();

        $this->actingAs($this->seller())
            ->post(route('order-claim-reject', $order->order_code), [
                'manual_review_note' => 'Transfer belum terlihat di mutasi.',
            ])
            ->assertRedirect(route('order-show', $order->order_code))
            ->assertSessionHas('error');

        $order->refresh();
        $this->assertSame('REJECTED', $order->manual_review_status);
        $this->assertNull($order->manual_claim_at);
        $this->assertSame(Order::STATUS_PENDING, $order->order_status);

        // Pembeli boleh mengirim ulang buktinya.
        $this->post(route('order-claim', $order->order_code), ['manual_claim_note' => 'sudah diulang']);
        $this->assertNotNull($order->fresh()->manual_claim_at);
        $this->assertNull($order->fresh()->manual_review_status);
    }

    public function test_produk_yang_sudah_dipesan_tidak_bisa_dihapus(): void
    {
        $product = $this->product();
        Order::factory()->create([
            'product_id' => $product->id,
            'product_name_snapshot' => $product->name,
            'unit_price_snapshot' => $product->price,
            'total_amount' => $product->price,
        ]);

        $this->actingAs($this->seller())
            ->delete("/products/{$product->id}")
            ->assertRedirect(route('product-list'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_pembuatan_pesanan_dibatasi_throttle(): void
    {
        // Rute publik: kuncinya alamat IP, 10 pesanan per menit.
        $limiter = RateLimiter::limiter('order-create');
        $this->assertNotNull($limiter);
        $limit = $limiter(Request::create('/checkout/1', 'POST'));
        $this->assertSame(10, $limit->maxAttempts);

        $claim = RateLimiter::limiter('order-claim');
        $this->assertSame(5, $claim(Request::create('/orders/ORD-1/claim', 'POST'))->maxAttempts);

        foreach (['order-store' => 'throttle:order-create', 'order-claim' => 'throttle:order-claim'] as $name => $middleware) {
            $this->assertContains($middleware, Route::getRoutes()->getByName($name)->gatherMiddleware());
        }

        // Halaman pesanan tetap terbuka tanpa pembatasan.
        $this->assertNotContains('throttle:order-create', Route::getRoutes()->getByName('order-show')->gatherMiddleware());
        $this->assertNotContains('throttle:order-create', Route::getRoutes()->getByName('checkout')->gatherMiddleware());
    }

    public function test_seeder_membuat_pesanan_contoh_untuk_semua_status(): void
    {
        $this->seed();

        $this->assertDatabaseCount('orders', 6);

        foreach (Order::FLOW as $status) {
            $this->assertGreaterThan(
                0,
                Order::where('order_status', $status)->count(),
                "Seeder seharusnya membuat contoh pesanan berstatus {$status}."
            );
        }

        // Setiap pesanan punya kode unik dan total yang konsisten.
        foreach (Order::all() as $order) {
            $this->assertMatchesRegularExpression('/^ORD-\d{8}-[A-Z0-9]{6}$/', $order->order_code);
            $this->assertSame($order->unit_price_snapshot * $order->quantity, $order->total_amount);
        }
    }
}
