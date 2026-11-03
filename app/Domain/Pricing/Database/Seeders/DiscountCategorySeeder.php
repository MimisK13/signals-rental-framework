<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Database\Seeders;

use App\Domain\Pricing\Models\DiscountCategory;
use Illuminate\Database\Seeder;

class DiscountCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Trade Discount', 'description' => 'Standard trade customer discount category.'],
            ['name' => 'Preferred Customer', 'description' => 'Enhanced discount category for preferred accounts.'],
            ['name' => 'Staff', 'description' => 'Internal staff discount category.'],
        ];

        foreach ($categories as $category) {
            DiscountCategory::query()->updateOrCreate(
                ['name' => $category['name']],
                [
                    'description' => $category['description'],
                    'is_active' => true,
                ],
            );
        }
    }
}
