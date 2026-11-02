<?php

declare(strict_types=1);

namespace App\Domain\Tax\Database\Seeders;

use App\Domain\Tax\Models\TaxRate;
use Illuminate\Database\Seeder;

final class TaxRateSeeder extends Seeder
{
    public function run(): void
    {
        TaxRate::query()->updateOrCreate(
            ['name' => 'Standard'],
            ['description' => 'UK standard rate', 'rate' => '20.0000', 'is_active' => true],
        );

        TaxRate::query()->updateOrCreate(
            ['name' => 'Reduced'],
            ['description' => 'UK reduced rate', 'rate' => '5.0000', 'is_active' => true],
        );

        TaxRate::query()->updateOrCreate(
            ['name' => 'Zero'],
            ['description' => 'Zero rated', 'rate' => '0.0000', 'is_active' => true],
        );
    }
}
