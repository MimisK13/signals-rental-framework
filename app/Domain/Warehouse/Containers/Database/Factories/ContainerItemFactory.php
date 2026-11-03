<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Containers\Database\Factories;

use App\Domain\Products\Models\Product;
use App\Domain\Stock\Models\StockLevel;
use App\Domain\Warehouse\Containers\Models\Container;
use App\Domain\Warehouse\Containers\Models\ContainerItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContainerItem>
 */
final class ContainerItemFactory extends Factory
{
    protected $model = ContainerItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'container_id' => Container::factory(),
            'serialised_item_id' => StockLevel::factory()->serialised(),
            'product_id' => Product::factory()->serialised(),
            'packed_at' => now(),
            'packed_by_user_id' => null,
            'unpacked_at' => null,
        ];
    }
}
