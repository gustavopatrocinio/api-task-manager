<?php

namespace Database\Factories;

use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = fake()->dateTimeBetween('-1 month', 'now');

        return [
            'user_id' => User::factory(),
            'plan_id' => Plan::factory(),
            'status' => SubscriptionStatus::Active,
            'starts_at' => $startsAt,
            'ends_at' => null,
            'trial_ends_at' => null,
            'cancelled_at' => null,
        ];
    }

    public function trialing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SubscriptionStatus::Trialing,
            'trial_ends_at' => now()->addDays(7),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SubscriptionStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
    }

    public function pastDue(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SubscriptionStatus::PastDue,
        ]);
    }
}
