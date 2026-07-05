<?php

namespace Database\Factories;

use App\Enums\MembershipType;
use App\Models\Member;
use App\Models\Product;
use App\Models\ProductSupplierCost;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductSupplierCost>
 */
class ProductSupplierCostFactory extends Factory
{
    protected $model = ProductSupplierCost::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'supplier_id' => Member::factory()->state(['membership_type' => MembershipType::Organisation]),
            'store_id' => null,
            'cost' => fake()->numberBetween(500, 50000),
            'currency_code' => 'GBP',
            'cost_basis' => 'per_unit',
            'supplier_sku' => fake()->optional()->bothify('SKU-####'),
            'lead_time_days' => fake()->optional()->numberBetween(1, 14),
            'min_order_quantity' => '1.00',
            'is_preferred' => false,
            'metadata' => null,
        ];
    }

    public function preferred(): static
    {
        return $this->state(fn (): array => [
            'is_preferred' => true,
        ]);
    }

    public function forStore(?Store $store = null): static
    {
        return $this->state(fn (): array => [
            'store_id' => $store !== null ? $store->id : Store::factory(),
        ]);
    }
}
