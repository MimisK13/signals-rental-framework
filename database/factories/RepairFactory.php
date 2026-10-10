<?php

namespace Database\Factories;

use App\Enums\RepairStatus;
use App\Models\Asset;
use App\Models\Product;
use App\Models\Repair;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Repair>
 */
class RepairFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'asset_id' => null,
            'product_id' => Product::factory()->bulk(),
            'store_id' => Store::factory(),
            'quantity' => 1,
            'status' => RepairStatus::Open,
            'reported_at' => now(),
            'started_at' => null,
            'expected_completed_at' => null,
            'completed_at' => null,
            'is_usable' => true,
            'reported_by_user_id' => User::factory(),
            'assigned_to_user_id' => null,
            'external_repairer_id' => null,
            'description' => 'Repair required',
            'notes' => null,
        ];
    }

    public function unusable(): static
    {
        return $this->state(fn (): array => ['is_usable' => false]);
    }

    public function inProgress(): static
    {
        return $this->state(fn (): array => [
            'status' => RepairStatus::InProgress,
            'started_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => RepairStatus::Completed,
            'completed_at' => now(),
        ]);
    }

    public function forBulk(Product $product, Store $store, int $quantity): static
    {
        return $this->state(fn (): array => [
            'asset_id' => null,
            'product_id' => $product->id,
            'store_id' => $store->id,
            'quantity' => $quantity,
        ]);
    }

    public function forAsset(Asset $asset): static
    {
        return $this->state(fn (): array => [
            'asset_id' => $asset->id,
            'product_id' => $asset->product_id,
            'quantity' => 1,
        ]);
    }
}
