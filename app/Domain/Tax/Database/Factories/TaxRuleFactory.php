<?php

declare(strict_types=1);

namespace App\Domain\Tax\Database\Factories;

use App\Domain\Tax\Enums\TaxResultType;
use App\Domain\Tax\Models\OrganisationTaxClass;
use App\Domain\Tax\Models\ProductTaxClass;
use App\Domain\Tax\Models\TaxRate;
use App\Domain\Tax\Models\TaxRule;
use App\Domain\Tax\Models\TaxZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaxRule>
 */
final class TaxRuleFactory extends Factory
{
    protected $model = TaxRule::class;

    public function definition(): array
    {
        return [
            'organisation_tax_class_id' => OrganisationTaxClass::factory(),
            'product_tax_class_id' => ProductTaxClass::factory(),
            'tax_rate_id' => TaxRate::factory(),
            'priority' => 0,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public function zoneMatch(array $overrides = []): static
    {
        return $this->state(fn (): array => array_merge([
            'name' => fake()->words(3, true),
            'organisation_tax_class_id' => null,
            'product_tax_class_id' => null,
            'tax_rate_id' => null,
            'seller_zone_id' => TaxZone::factory(),
            'buyer_zone_id' => TaxZone::factory(),
            'buyer_type' => null,
            'has_tax_id' => null,
            'tax_category_id' => null,
            'result_type' => TaxResultType::Standard,
            'legal_text' => null,
            'priority' => 10,
            'is_active' => true,
        ], $overrides));
    }
}
