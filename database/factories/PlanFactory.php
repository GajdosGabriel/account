<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Plan>
 */
class PlanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'key' => fake()->unique()->slug(1),
            'name' => fake()->word(),
            'price_cents' => fake()->randomElement([0, 1900, 4900, 9900]),
            'currency' => 'EUR',
            'interval' => 'month',
            'trial_days' => 0,
            'features' => [],
            'is_active' => true,
        ];
    }
}
