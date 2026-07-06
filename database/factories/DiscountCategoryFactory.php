<?php

namespace Database\Factories;

use App\Models\DiscountCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiscountCategory>
 */
class DiscountCategoryFactory extends Factory
{
    protected $model = DiscountCategory::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
