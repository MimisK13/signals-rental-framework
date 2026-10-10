<?php

namespace Database\Factories;

use App\Models\InspectionPolicy;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InspectionPolicy>
 */
class InspectionPolicyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory()->serialised(),
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'interval_months' => 12,
            'is_active' => true,
            'effective_from' => now()->toDateString(),
        ];
    }
}
