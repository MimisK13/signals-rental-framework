<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Containers\Database\Factories;

use App\Domain\Stock\Models\StockLevel;
use App\Domain\Stores\Models\Store;
use App\Domain\Warehouse\Containers\Models\Container;
use App\Domain\Warehouse\Containers\Models\ContainerBulkMembership;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContainerBulkMembership>
 */
final class ContainerBulkMembershipFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $store = Store::factory();

        return [
            'container_id' => Container::factory()->temporary()->state(['store_id' => $store]),
            'stock_level_id' => StockLevel::factory()->bulk()->state(['store_id' => $store]),
            'quantity' => '1.00',
        ];
    }
}
