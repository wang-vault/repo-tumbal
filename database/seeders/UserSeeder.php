<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Akun penjual untuk demo. Ganti password-nya sebelum dipakai sungguhan.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@anubis.test'],
            [
                'name' => 'Penjual Anubis',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
    }
}
