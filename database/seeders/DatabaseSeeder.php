<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Jalankan: php artisan migrate --seed
     *
     * Urutannya penting: OrderSeeder menyalin nama & harga dari produk yang
     * dibuat ProductSeeder, jadi harus jalan sesudahnya.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            ProductSeeder::class,
            OrderSeeder::class,
        ]);
    }
}
