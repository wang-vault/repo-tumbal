<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Nama dibatasi 120 karakter (aturan yang sama dengan form produk).
            'name' => Str::limit(fake()->words(4, true), 100, ''),
            'description' => fake()->paragraph(),
            // Rupiah penuh, kelipatan seribu: Rp10.000 - Rp900.000.
            'price' => fake()->numberBetween(10, 900) * 1000,
            'image_url' => null,
            'is_active' => true,
        ];
    }

    /**
     * Produk nonaktif: tidak boleh muncul di beranda maupun katalog publik.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
