<?php

namespace Database\Factories;

use App\Models\Container;
use App\Models\ContainerBulkMembership;
use App\Models\StockLevel;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContainerBulkMembership>
 */
class ContainerBulkMembershipFactory extends Factory
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
