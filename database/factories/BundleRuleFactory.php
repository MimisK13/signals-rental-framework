<?php

namespace Database\Factories;

use App\Models\BundleRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BundleRule>
 */
class BundleRuleFactory extends Factory
{
    protected $model = BundleRule::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'bundle_percent' => '10.00',
            'bundle_fixed_price_minor' => null,
            'criteria' => [],
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    /**
     * @param  list<int>  $productIds
     */
    public function forProducts(array $productIds): static
    {
        return $this->state(fn (): array => [
            'criteria' => ['product_ids' => $productIds],
        ]);
    }

    public function fixed(int $minor): static
    {
        return $this->state(fn (): array => [
            'bundle_percent' => null,
            'bundle_fixed_price_minor' => $minor,
        ]);
    }

    public function percent(string $percent = '10.00'): static
    {
        return $this->state(fn (): array => [
            'bundle_percent' => $percent,
            'bundle_fixed_price_minor' => null,
        ]);
    }
}
