<?php

namespace Database\Factories;

use App\Models\PriceCategory;
use App\Models\PriceCategoryTier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PriceCategoryTier>
 */
class PriceCategoryTierFactory extends Factory
{
    protected $model = PriceCategoryTier::class;

    public function definition(): array
    {
        return [
            'price_category_id' => PriceCategory::factory(),
            'tier_number' => 1,
            'label' => 'Day 1',
            'multiplier' => '1.0000',
            'sort_order' => 0,
        ];
    }
}
