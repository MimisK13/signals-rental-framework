<?php

namespace Database\Factories;

use App\Models\PriceCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PriceCategory>
 */
class PriceCategoryFactory extends Factory
{
    protected $model = PriceCategory::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }

    public function withDefaultTiers(): static
    {
        return $this->afterCreating(function (PriceCategory $category): void {
            $category->tiers()->createMany([
                [
                    'tier_number' => 1,
                    'label' => 'Day 1',
                    'multiplier' => '1.0000',
                    'sort_order' => 0,
                ],
                [
                    'tier_number' => 2,
                    'label' => 'Day 2+',
                    'multiplier' => '0.8000',
                    'sort_order' => 1,
                ],
            ]);
        });
    }
}
