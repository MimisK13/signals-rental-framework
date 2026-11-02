<?php

declare(strict_types=1);

namespace App\Domain\Tax\Database\Seeders;

use App\Domain\Tax\Models\OrganisationTaxClass;
use App\Domain\Tax\Models\ProductTaxClass;
use Illuminate\Database\Seeder;

final class TaxClassSeeder extends Seeder
{
    public function run(): void
    {
        OrganisationTaxClass::query()->updateOrCreate(
            ['name' => 'Standard'],
            ['description' => 'Standard tax treatment', 'is_default' => true],
        );

        ProductTaxClass::query()->updateOrCreate(
            ['name' => 'Standard'],
            ['description' => 'Standard tax rate', 'is_default' => true],
        );

        ProductTaxClass::query()->updateOrCreate(
            ['name' => 'Exempt'],
            ['description' => 'Tax exempt', 'is_default' => false],
        );

        $defaultProductClass = ProductTaxClass::query()->where('is_default', true)->first();

        if ($defaultProductClass !== null && settings('tax.default_product_tax_class_id') === null) {
            settings()->set('tax.default_product_tax_class_id', $defaultProductClass->id);
        }
    }
}
