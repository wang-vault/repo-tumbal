<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Snapshot nama & harga diambil dari produk sungguhan, sama seperti
        // yang dilakukan CheckoutController saat pesanan dibuat.
        $product = Product::factory()->create();
        $quantity = fake()->numberBetween(1, Order::MAX_QUANTITY);

        return [
            'order_code' => Order::generateUniqueCode(),
            'product_id' => $product->id,
            'product_name_snapshot' => $product->name,
            'unit_price_snapshot' => $product->price,
            'quantity' => $quantity,
            'total_amount' => $product->price * $quantity,
            'payment_method' => 'MANUAL',
            'payment_status' => Order::STATUS_PENDING,
            'order_status' => Order::STATUS_PENDING,
            'buyer_name_snapshot' => fake()->name(),
            // Format final nomor WhatsApp Indonesia: 62 + 8..13 digit.
            'buyer_whatsapp_snapshot' => '628'.fake()->numerify('#########'),
            'buyer_email_snapshot' => null,
        ];
    }

    /**
     * Pembeli sudah menekan "saya sudah transfer", menunggu verifikasi penjual.
     */
    public function claimed(): static
    {
        return $this->state(fn (array $attributes) => [
            'manual_claim_at' => now()->subHours(3),
            'manual_claim_note' => 'Sudah transfer lewat m-banking, atas nama pembeli.',
            'manual_claim_reference' => strtoupper(fake()->bothify('TRX##??####')),
        ]);
    }

    /**
     * Pembayaran sudah diverifikasi penjual.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'order_status' => Order::STATUS_PAID,
            'payment_status' => 'PAID',
            'paid_at' => now()->subDay(),
            'manual_claim_at' => now()->subDays(1)->subHours(2),
            'manual_reviewed_at' => now()->subDay(),
            'manual_review_status' => 'APPROVED',
        ]);
    }

    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'order_status' => Order::STATUS_PROCESSING,
            'payment_status' => 'PAID',
            'paid_at' => now()->subDays(2),
            'manual_reviewed_at' => now()->subDays(2),
            'manual_review_status' => 'APPROVED',
        ]);
    }

    /**
     * Pesanan selesai — yang muncul di halaman testimoni.
     */
    public function done(): static
    {
        return $this->state(fn (array $attributes) => [
            'order_status' => Order::STATUS_DONE,
            'payment_status' => 'PAID',
            'paid_at' => now()->subDays(6),
            'manual_reviewed_at' => now()->subDays(6),
            'manual_review_status' => 'APPROVED',
        ]);
    }
}
