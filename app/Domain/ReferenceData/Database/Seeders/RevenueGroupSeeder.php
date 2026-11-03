<?php

declare(strict_types=1);

namespace App\Domain\ReferenceData\Database\Seeders;

use App\Domain\ReferenceData\Models\RevenueGroup;
use Illuminate\Database\Seeder;

class RevenueGroupSeeder extends Seeder
{
    public function run(): void
    {
        $groups = ['Dry Hire', 'Wet Hire', 'Sales', 'Services'];

        foreach ($groups as $name) {
            RevenueGroup::query()->updateOrCreate(
                ['name' => $name],
                ['is_active' => true],
            );
        }
    }
}
