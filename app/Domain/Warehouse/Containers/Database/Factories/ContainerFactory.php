<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Containers\Database\Factories;

use App\Domain\Stock\Models\StockLevel;
use App\Domain\Warehouse\Containers\Enums\ContainerAvailabilityMode;
use App\Domain\Warehouse\Containers\Enums\ContainerScanMode;
use App\Domain\Warehouse\Containers\Enums\ContainerStatus;
use App\Domain\Warehouse\Containers\Models\Container;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Container>
 */
final class ContainerFactory extends Factory
{
    protected $model = Container::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $store = Store::factory();

        return [
            'uuid' => (string) Str::uuid(),
            'name' => fake()->words(2, true).' Case',
            'serialised_item_id' => StockLevel::factory()->serialised(),
            'product_id' => Product::factory()->containerable(),
            'is_temporary' => false,
            'barcode' => fake()->unique()->numerify('CASE-####'),
            'store_id' => $store,
            'scan_mode' => ContainerScanMode::Strict->value,
            'status' => ContainerStatus::Open->value,
        ];
    }

    /**
     * A container backed by a kit-mode containerable product.
     */
    public function kit(): static
    {
        return $this->state(fn (): array => [
            'product_id' => Product::factory()->containerable(ContainerAvailabilityMode::Kit),
        ]);
    }

    /**
     * A container backed by a hybrid-mode containerable product.
     */
    public function hybrid(): static
    {
        return $this->state(fn (): array => [
            'product_id' => Product::factory()->containerable(ContainerAvailabilityMode::Hybrid),
        ]);
    }

    /**
     * A container backed by a transport-mode containerable product.
     */
    public function transport(): static
    {
        return $this->state(fn (): array => [
            'product_id' => Product::factory()->containerable(ContainerAvailabilityMode::Transport),
        ]);
    }

    public function sealed(): static
    {
        return $this->state(fn (): array => [
            'status' => ContainerStatus::Sealed->value,
            'sealed_at' => now(),
        ]);
    }

    /**
     * A barcode-only temporary container with no backing product or asset.
     */
    public function temporary(): static
    {
        return $this->state(fn (): array => [
            'serialised_item_id' => null,
            'product_id' => null,
            'is_temporary' => true,
            'scan_mode' => ContainerScanMode::Free->value,
        ]);
    }
}
