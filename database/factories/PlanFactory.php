<?php

namespace Database\Factories;

use App\Enums\PlanInterval;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->words(2, true);

        return [
            'name' => ucfirst($name),
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'description' => fake()->sentence(),
            'price' => fake()->randomFloat(2, 9.99, 199.99),
            'currency' => 'BRL',
            'interval' => fake()->randomElement(PlanInterval::cases()),
            'interval_count' => 1,
            'trial_days' => fake()->randomElement([0, 7, 14]),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function monthly(): static
    {
        return $this->state(fn (array $attributes) => [
            'interval' => PlanInterval::Monthly,
        ]);
    }

    public function yearly(): static
    {
        return $this->state(fn (array $attributes) => [
            'interval' => PlanInterval::Yearly,
        ]);
    }
}
