<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Menjalankan: php artisan test --filter=ProductTest
 *
 * Rute tulis (store/update/destroy) dilindungi middleware `auth` dan dibatasi
 * `throttle:product-write`, jadi semua pengujian di bawah ini masuk dulu sebagai
 * penjual lewat actingAs(). Perilaku tamu diuji terpisah di AuthTest.php.
 */
class ProductTest extends TestCase
{
    use RefreshDatabase;

    private function seller(): User
    {
        return User::factory()->create();
    }

    public function test_beranda_menampilkan_produk_aktif(): void
    {
        Product::factory()->create(['name' => 'Kopi Tubruk Nusantara']);

        $this->get('/')
            ->assertOk()
            ->assertSee('Kopi Tubruk Nusantara');
    }

    public function test_beranda_punya_form_pencarian_produk(): void
    {
        Product::factory()->create([
            'name' => 'Kaos Kabar Pagi',
            'description' => 'Bahan katun adem, sablon tangan.',
        ]);

        // Kotak pencarian di hero beranda mengarah ke katalog (?q=...).
        $this->get('/')
            ->assertOk()
            ->assertSee('Cari di katalog')
            ->assertSee('name="q"', false)
            ->assertSee('action="'.route('product-list').'"', false);

        // Dan memang menghasilkan produk yang cocok.
        $this->get('/products?q=Kabar')
            ->assertOk()
            ->assertSee('Kaos Kabar Pagi');

        $this->get('/products?q=sablon+tangan')
            ->assertOk()
            ->assertSee('Kaos Kabar Pagi');
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
        $response = $this->actingAs($this->seller())->post('/products', [
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
        $this->actingAs($this->seller())->post('/products', [
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
        $response = $this->actingAs($this->seller())->post('/products', [
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

        $this->actingAs($this->seller())->put("/products/{$product->id}", [
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

        $this->actingAs($this->seller())
            ->delete("/products/{$product->id}")
            ->assertRedirect(route('product-list'));

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_halaman_detail_produk_dapat_diakses_tanpa_login(): void
    {
        $product = Product::factory()->create([
            'name' => 'Tote Bag Kanvas Edisi Koran',
            'price' => 65000,
        ]);

        $this->get("/products/{$product->id}")
            ->assertOk()
            ->assertSee('Tote Bag Kanvas Edisi Koran')
            ->assertSee('Rp65.000')
            ->assertDontSee('Ubah Produk');
    }

    public function test_produk_yang_tidak_ada_mengembalikan_404(): void
    {
        // 404 dirender lewat resources/views/errors/404.blade.php, bukan halaman
        // bawaan Laravel.
        $this->get('/products/9999')
            ->assertNotFound()
            ->assertSee('Halaman tidak ditemukan')
            ->assertSee('error-card', false);
    }

    public function test_alamat_tak_dikenal_menampilkan_halaman_404_bergaya(): void
    {
        $this->get('/alamat-yang-tidak-ada')
            ->assertNotFound()
            ->assertSee('Halaman tidak ditemukan')
            ->assertSee('error-card', false);
    }

    public function test_katalog_dipaginasi_dan_nomor_baris_melanjut(): void
    {
        for ($i = 1; $i <= 15; $i++) {
            Product::factory()->create([
                'name' => sprintf('Kopi Uji %02d', $i),
                'price' => 20000,
            ]);
        }

        // Halaman pertama: 12 produk terbaru, nomor baris mulai dari 1.
        $this->get(route('product-list'))
            ->assertOk()
            ->assertSee('Kopi Uji 15')
            ->assertDontSee('Kopi Uji 01')
            ->assertSee('<td>1</td>', false);

        // Halaman kedua: sisanya, nomor baris melanjutkan dari 13.
        $this->get(route('product-list', ['page' => 2]))
            ->assertOk()
            ->assertSee('Kopi Uji 01')
            ->assertDontSee('Kopi Uji 15')
            ->assertSee('<td>13</td>', false)
            ->assertSee('Halaman 2 dari 2', false);

        // Kata kunci pencarian ikut terbawa ke tautan halaman lain.
        $this->get(route('product-list', ['q' => 'Kopi Uji', 'page' => 2]))
            ->assertOk()
            ->assertSee('q=Kopi', false);
    }

    public function test_rute_tulis_produk_dibatasi_throttle(): void
    {
        $limiter = RateLimiter::limiter('product-write');
        $this->assertNotNull($limiter, 'Limiter product-write harus terdaftar di AppServiceProvider.');

        $seller = $this->seller();
        $limit = $limiter(Request::create('/products', 'POST')->setUserResolver(fn () => $seller));

        $this->assertSame(20, $limit->maxAttempts);
        $this->assertSame((string) $seller->id, $limit->key);

        // Hanya aksi menulis yang dibatasi; membuka form dan membaca katalog tidak.
        foreach (['product-store', 'product-update', 'product-destroy'] as $name) {
            $this->assertContains(
                'throttle:product-write',
                Route::getRoutes()->getByName($name)->gatherMiddleware(),
                "Rute {$name} seharusnya dibatasi throttle:product-write."
            );
        }

        foreach (['product-create', 'product-edit', 'product-list'] as $name) {
            $this->assertNotContains(
                'throttle:product-write',
                Route::getRoutes()->getByName($name)->gatherMiddleware(),
                "Rute {$name} seharusnya tidak dibatasi throttle."
            );
        }
    }

    public function test_seeder_mengisi_katalog_dan_akun_penjual(): void
    {
        $this->seed();

        $this->assertDatabaseCount('products', 10);
        $this->assertDatabaseHas('users', ['email' => 'admin@anubis.test']);
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
