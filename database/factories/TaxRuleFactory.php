<?php

namespace Database\Factories;

use App\Enums\TaxResultType;
use App\Models\OrganisationTaxClass;
use App\Models\ProductTaxClass;
use App\Models\TaxRate;
use App\Models\TaxRule;
use App\Models\TaxZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaxRule>
 */
class TaxRuleFactory extends Factory
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
