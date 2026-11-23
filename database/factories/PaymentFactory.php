<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'user_id' => User::factory(),
            'provider' => 'manual',
            'provider_payment_id' => null,
            'payment_method' => 'qris',
            'amount_idr' => fake()->numberBetween(1000, 100000),
            'currency' => 'IDR',
            'status' => 'pending',
            'reference_code' => 'PAY-'.strtoupper(Str::random(10)),
            'payment_token' => rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '='),
            'qr_url' => null,
            'expires_at' => now()->addMinutes(30),
            'paid_at' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'paid',
            'paid_at' => now(),
        ]);
    }
}
