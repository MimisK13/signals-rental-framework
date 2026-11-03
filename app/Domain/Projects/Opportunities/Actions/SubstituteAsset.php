<?php

declare(strict_types=1);

namespace App\Domain\Projects\Opportunities\Actions;

use App\Concerns\CommitsVerbsEvents;
use App\Domain\Availability\Services\OpportunityItemDemandResolver;
use App\Domain\Availability\Services\ProductStoreCapacityLock;
use App\Domain\Projects\Opportunities\Data\OpportunityItemAssetData;
use App\Domain\Projects\Opportunities\Data\SubstituteAssetData;
use App\Domain\Projects\Opportunities\Models\OpportunityItemAsset;
use App\Domain\Stock\Models\StockLevel;
use App\Domain\Projects\Opportunities\Verbs\Events\AssetSubstituted;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * Swaps the physical asset an assignment points at via the AssetSubstituted event,
 * preserving the assignment's current status.
 */
class SubstituteAsset
{
    use CommitsVerbsEvents;

    public function __invoke(OpportunityItemAsset $asset, SubstituteAssetData $data): OpportunityItemAssetData
    {
        Gate::authorize('update', $asset->item->opportunity);

        $stateId = $asset->state_id;
        $substitutedAt = Carbon::now('UTC')->toIso8601String();

        $this->commitVerbs(function () use ($stateId, $data, $substitutedAt): void {
            $assignment = OpportunityItemAsset::query()
                ->with(['stockLevel', 'item'])
                ->where('state_id', $stateId)
                ->firstOrFail();
            $item = $assignment->item;
            $newStockLevel = StockLevel::query()->findOrFail($data->new_stock_level_id);
            $context = app(OpportunityItemDemandResolver::class)->resolveContext($item);

            app(ProductStoreCapacityLock::class)->acquireMany(array_values(array_filter([
                $context['product_id'] !== null && $assignment->stockLevel !== null
                    ? ['product_id' => $context['product_id'], 'store_id' => $assignment->stockLevel->store_id]
                    : null,
                $context['product_id'] !== null
                    ? ['product_id' => $context['product_id'], 'store_id' => $newStockLevel->store_id]
                    : null,
            ])));

            AssetSubstituted::fire(
                state_id: $stateId,
                new_stock_level_id: $data->new_stock_level_id,
                reason: $data->reason,
                substituted_at: $substitutedAt,
            );
        });

        return OpportunityItemAssetData::fromModel($asset->fresh(['stockLevel']));
    }
}
