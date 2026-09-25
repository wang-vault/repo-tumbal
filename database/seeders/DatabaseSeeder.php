<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Jalankan: php artisan migrate --seed
     */
    public function run(): void
    {
        $this->call([
            ProductSeeder::class,
        ]);
    }
}
