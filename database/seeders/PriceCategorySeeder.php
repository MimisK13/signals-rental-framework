<?php

namespace Database\Seeders;

use App\Models\PriceCategory;
use Illuminate\Database\Seeder;

class PriceCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Trade',
                'description' => 'Trade customer price list.',
                'tiers' => [
                    ['tier_number' => 1, 'label' => 'Day 1', 'multiplier' => '1.0000', 'sort_order' => 0],
                    ['tier_number' => 2, 'label' => 'Day 2+', 'multiplier' => '0.8000', 'sort_order' => 1],
                ],
            ],
            [
                'name' => 'Retail',
                'description' => 'Retail customer price list.',
                'tiers' => [
                    ['tier_number' => 1, 'label' => 'Day 1', 'multiplier' => '1.0000', 'sort_order' => 0],
                ],
            ],
        ];

        foreach ($categories as $definition) {
            $category = PriceCategory::query()->updateOrCreate(
                ['name' => $definition['name']],
                [
                    'description' => $definition['description'],
                    'is_active' => true,
                ],
            );

            $category->tiers()->delete();

            foreach ($definition['tiers'] as $tier) {
                $category->tiers()->create($tier);
            }
        }
    }
}
