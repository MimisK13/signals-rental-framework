<?php

namespace App\Services\VirtualStock;

use App\Enums\StockMethod;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\VirtualStockIntake;
use App\Services\SequenceAllocator;
use App\Verbs\Events\VirtualStock\VirtualStockIntakeSerialisedAssetsProvisioned;
use Carbon\CarbonImmutable;

/**
 * Coordinates serialised virtual asset provisioning inside Verbs commits.
 */
class SerialisedVirtualStockAssetCoordinator
{
    public function isSerialisedIntake(VirtualStockIntake $intake): bool
    {
        $product = Product::query()->find($intake->product_id);

        return $product !== null && $product->stock_method === StockMethod::Serialised;
    }

    public function needsProvisioning(VirtualStockIntake $intake): bool
    {
        if (! $this->isSerialisedIntake($intake)) {
            return false;
        }

        return ! StockLevel::query()
            ->where('virtual_stock_intake_id', $intake->id)
            ->where('is_virtual', true)
            ->exists();
    }

    public function fireProvisionedEvent(VirtualStockIntake $intake, CarbonImmutable $at): void
    {
        if (! $this->isSerialisedIntake($intake)) {
            return;
        }

        $assets = $this->buildProvisionPayload($intake);

        VirtualStockIntakeSerialisedAssetsProvisioned::fire(
            state_id: (int) $intake->state_id,
            assets: $assets,
            provisioned_at: $at->toIso8601String(),
        );
    }

    /**
     * @return list<array{stock_level_id: int, supply_demand_id: int, sequence: int, asset_number: string}>
     */
    public function buildProvisionPayload(VirtualStockIntake $intake): array
    {
        $allocator = app(SequenceAllocator::class);
        $assets = [];

        for ($sequence = 1; $sequence <= $intake->quantity; $sequence++) {
            $assets[] = [
                'stock_level_id' => $allocator->next('stock_levels'),
                'supply_demand_id' => $allocator->next('demands'),
                'sequence' => $sequence,
                'asset_number' => VirtualStockAssetProvisioner::assetNumberFor($intake, $sequence),
            ];
        }

        return $assets;
    }

    public function shouldRetireAssets(VirtualStockIntake $intake): bool
    {
        return StockLevel::query()
            ->where('virtual_stock_intake_id', $intake->id)
            ->where('is_virtual', true)
            ->where(function ($query): void {
                $query->whereNull('virtual_asset_status')
                    ->orWhere('virtual_asset_status', '!=', 'retired');
            })
            ->exists();
    }
}
