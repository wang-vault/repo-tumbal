<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Menjalankan: php artisan test --filter=AuthTest
 */
class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function seller(): User
    {
        return User::factory()->create([
            'password' => bcrypt('rahasia-123'),
        ]);
    }

    public function test_tamu_melihat_halaman_login(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Area penjual')
            ->assertSee('admin@anubis.test');
    }

    public function test_tamu_diarahkan_ke_login_saat_membuka_form_tambah_produk(): void
    {
        $this->get('/products/create')->assertRedirect(route('login'));
    }

    public function test_tamu_tidak_bisa_menyimpan_produk(): void
    {
        $this->post('/products', [
            'name' => 'Produk Selundupan',
            'price' => 20000,
            'is_active' => '1',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseCount('products', 0);
    }

    public function test_tamu_tidak_bisa_mengubah_atau_menghapus_produk(): void
    {
        $product = Product::factory()->create();

        $this->put("/products/{$product->id}", ['name' => 'Diubah Tamu', 'price' => 30000])
            ->assertRedirect(route('login'));
        $this->delete("/products/{$product->id}")->assertRedirect(route('login'));

        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => $product->name]);
    }

    public function test_login_berhasil_dengan_kredensial_benar(): void
    {
        $user = $this->seller();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'rahasia-123',
        ])->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_gagal_dengan_password_salah(): void
    {
        $user = $this->seller();

        $this->from('/login')
            ->post('/login', ['email' => $user->email, 'password' => 'bukan-ini'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_divalidasi(): void
    {
        $this->from('/login')
            ->post('/login', ['email' => 'bukan-email', 'password' => ''])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['email', 'password']);
    }

    public function test_percobaan_login_dibatasi_lima_kali(): void
    {
        $user = $this->seller();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'salah']);
        }

        $response = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'salah',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString(
            'Terlalu banyak percobaan masuk',
            session('errors')->first('email')
        );
    }

    public function test_penjual_yang_sudah_masuk_dipantulkan_dari_halaman_login(): void
    {
        $this->actingAs($this->seller())
            ->get('/login')
            ->assertRedirect(route('home'));
    }

    public function test_logout_mengembalikan_status_tamu(): void
    {
        $this->actingAs($this->seller())
            ->post('/logout')
            ->assertRedirect(route('home'));

        $this->assertGuest();
    }

    public function test_penjual_bisa_membuka_form_tambah_produk(): void
    {
        $this->actingAs($this->seller())
            ->get('/products/create')
            ->assertOk()
            ->assertSee('Tambah Produk');
    }

    public function test_tamu_hanya_melihat_produk_aktif_di_katalog(): void
    {
        Product::factory()->create(['name' => 'ProdukUntukPembeli']);
        Product::factory()->inactive()->create(['name' => 'ProdukArsipPenjual']);

        $this->get('/products')
            ->assertOk()
            ->assertSee('ProdukUntukPembeli')
            ->assertDontSee('ProdukArsipPenjual')
            ->assertDontSee('Hapus');

        $this->actingAs($this->seller())
            ->get('/products')
            ->assertOk()
            ->assertSee('ProdukArsipPenjual')
            ->assertSee('Hapus');
    }

    public function test_seeder_membuat_akun_penjual_yang_bisa_dipakai_login(): void
    {
        $this->seed();

        $this->post('/login', [
            'email' => 'admin@anubis.test',
            'password' => 'password',
        ])->assertRedirect(route('home'));

        $this->assertAuthenticated();
    }
}
