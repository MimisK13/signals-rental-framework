<?php

declare(strict_types=1);

namespace App\Domain\Products\Database\Factories;

use App\Domain\Products\Enums\KitComponentBinding;
use App\Domain\Products\Models\Product;
use App\Domain\Products\Models\SerialisedComponent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SerialisedComponent>
 */
class SerialisedComponentFactory extends Factory
{
    protected $model = SerialisedComponent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'component_product_id' => Product::factory(),
            'quantity' => 1,
            'binding' => KitComponentBinding::Pool->value,
            'sort_order' => 0,
        ];
    }

    public function fixed(): static
    {
        return $this->state(fn (): array => [
            'binding' => KitComponentBinding::Fixed->value,
        ]);
    }

    public function pool(): static
    {
        return $this->state(fn (): array => [
            'binding' => KitComponentBinding::Pool->value,
        ]);
    }

    public function quantity(float|int $quantity): static
    {
        return $this->state(fn (): array => [
            'quantity' => $quantity,
        ]);
    }
}
