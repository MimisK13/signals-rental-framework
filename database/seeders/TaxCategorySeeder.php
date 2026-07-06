<?php

namespace Database\Seeders;

use App\Enums\TaxCategoryType;
use App\Models\TaxCategory;
use Illuminate\Database\Seeder;

class TaxCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Standard', 'slug' => 'standard', 'type' => TaxCategoryType::Standard, 'is_default' => true],
            ['name' => 'Reduced', 'slug' => 'reduced', 'type' => TaxCategoryType::Reduced],
            ['name' => 'Zero Rated', 'slug' => 'zero', 'type' => TaxCategoryType::Zero],
            ['name' => 'Exempt', 'slug' => 'exempt', 'type' => TaxCategoryType::Exempt],
        ];

        foreach ($categories as $category) {
            TaxCategory::query()->updateOrCreate(
                ['slug' => $category['slug']],
                [
                    'name' => $category['name'],
                    'type' => $category['type'],
                    'description' => null,
                    'is_default' => $category['is_default'] ?? false,
                    'is_active' => true,
                ],
            );
        }
    }
}
