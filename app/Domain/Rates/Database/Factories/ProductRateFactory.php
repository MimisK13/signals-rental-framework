<?php

declare(strict_types=1);

namespace App\Domain\Rates\Database\Factories;

use App\Domain\Products\Models\Product;
use App\Domain\Rates\Enums\RateTransactionType;
use App\Domain\Rates\Models\ProductRate;
use App\Domain\Rates\Models\RateDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductRate>
 */
final class ProductRateFactory extends Factory
{
    protected $model = ProductRate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'rate_definition_id' => RateDefinition::factory(),
            'store_id' => null,
            'transaction_type' => RateTransactionType::Rental,
            'price' => fake()->numberBetween(1000, 50000),
            'currency' => 'GBP',
            'price_category_id' => null,
            'valid_from' => null,
            'valid_to' => null,
            'priority' => 0,
        ];
    }

    public function forSale(): static
    {
        return $this->state(fn (): array => [
            'transaction_type' => RateTransactionType::Sale,
        ]);
    }

    public function withPriority(int $priority): static
    {
        return $this->state(fn (): array => [
            'priority' => $priority,
        ]);
    }

    public function forPriceCategory(?int $priceCategoryId): static
    {
        return $this->state(fn (): array => [
            'price_category_id' => $priceCategoryId,
        ]);
    }
}
