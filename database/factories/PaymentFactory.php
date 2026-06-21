<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subscription_id' => Subscription::factory(),
            'amount' => fake()->randomFloat(2, 9.99, 199.99),
            'currency' => 'BRL',
            'status' => PaymentStatus::Pending,
            'payment_method' => fake()->randomElement(['pix', 'credit_card', 'boleto']),
            'reference' => null,
            'paid_at' => null,
            'confirmed_by' => null,
            'notes' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::Failed,
        ]);
    }
}
