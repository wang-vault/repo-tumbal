<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Menjalankan: php artisan test --filter=ProductTest
 */
class ProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_beranda_menampilkan_produk_aktif(): void
    {
        Product::factory()->create(['name' => 'Kopi Tubruk Nusantara']);

        $this->get('/')
            ->assertOk()
            ->assertSee('Kopi Tubruk Nusantara');
    }

    public function test_beranda_menyembunyikan_produk_nonaktif(): void
    {
        Product::factory()->inactive()->create(['name' => 'BarangArsipRahasia']);

        $this->get('/')
            ->assertOk()
            ->assertDontSee('BarangArsipRahasia');
    }

    public function test_katalog_menampilkan_produk_dan_mendukung_pencarian(): void
    {
        Product::factory()->create(['name' => 'Mug Keramik Ink']);
        Product::factory()->create(['name' => 'Tote Bag Kanvas']);

        $this->get('/products')
            ->assertOk()
            ->assertSee('Mug Keramik Ink')
            ->assertSee('Tote Bag Kanvas');

        $this->get('/products?q=mug')
            ->assertOk()
            ->assertSee('Mug Keramik Ink')
            ->assertDontSee('Tote Bag Kanvas');
    }

    public function test_produk_baru_bisa_disimpan(): void
    {
        $response = $this->post('/products', [
            'name' => 'Notebook Edisi Harian',
            'description' => 'Buku catatan A5 sampul keras',
            'price' => 38000,
            'image_url' => '',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('product-list'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('products', [
            'name' => 'Notebook Edisi Harian',
            'price' => 38000,
            'is_active' => true,
        ]);
    }

    public function test_produk_tanpa_centang_aktif_tersimpan_sebagai_nonaktif(): void
    {
        $this->post('/products', [
            'name' => 'Stiker Pack Anubis',
            'price' => 15000,
            'is_active' => '0',
        ])->assertRedirect(route('product-list'));

        $this->assertDatabaseHas('products', [
            'name' => 'Stiker Pack Anubis',
            'is_active' => false,
        ]);
    }

    public function test_validasi_menolak_nama_pendek_dan_harga_di_bawah_minimum(): void
    {
        $response = $this->post('/products', [
            'name' => 'A',
            'price' => 500,
            'is_active' => '1',
        ]);

        $response->assertSessionHasErrors(['name', 'price']);
        $this->assertDatabaseCount('products', 0);
    }

    public function test_produk_bisa_diubah(): void
    {
        $product = Product::factory()->create(['price' => 20000]);

        $this->put("/products/{$product->id}", [
            'name' => 'Kaos Kabar Pagi',
            'description' => 'Katun combed 24s',
            'price' => 89000,
            'image_url' => '',
            'is_active' => '0',
        ])->assertRedirect(route('product-list'));

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Kaos Kabar Pagi',
            'price' => 89000,
            'is_active' => false,
        ]);
    }

    public function test_produk_bisa_dihapus(): void
    {
        $product = Product::factory()->create();

        $this->delete("/products/{$product->id}")
            ->assertRedirect(route('product-list'));

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_halaman_detail_produk_dapat_diakses(): void
    {
        $product = Product::factory()->create([
            'name' => 'Tote Bag Kanvas Edisi Koran',
            'price' => 65000,
        ]);

        $this->get("/products/{$product->id}")
            ->assertOk()
            ->assertSee('Tote Bag Kanvas Edisi Koran')
            ->assertSee('Rp65.000');
    }

    public function test_produk_yang_tidak_ada_mengembalikan_404(): void
    {
        $this->get('/products/9999')->assertNotFound();
    }

    public function test_seeder_mengisi_katalog(): void
    {
        $this->seed();

        $this->assertDatabaseCount('products', 10);
        $this->get('/products')
            ->assertOk()
            ->assertSee('Kopi Tubruk Nusantara 200 g');
    }

    public function test_halaman_statis_dapat_diakses(): void
    {
        $this->get('/about')->assertOk()->assertSee('Transfer manual');
        $this->get('/downloader')->assertOk()->assertSee('Downloader');
    }
}
