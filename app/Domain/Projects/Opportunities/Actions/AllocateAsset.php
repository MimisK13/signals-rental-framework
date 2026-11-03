<?php

declare(strict_types=1);

namespace App\Domain\Projects\Opportunities\Actions;

use App\Concerns\CommitsVerbsEvents;
use App\Domain\Availability\Services\OpportunityItemDemandResolver;
use App\Domain\Availability\Services\ProductStoreCapacityLock;
use App\Domain\Projects\Opportunities\Data\AllocateAssetData;
use App\Domain\Projects\Opportunities\Data\OpportunityItemAssetData;
use App\Domain\Projects\Opportunities\Models\OpportunityItem;
use App\Domain\Projects\Opportunities\Models\OpportunityItemAsset;
use App\Domain\Stock\Models\StockLevel;
use App\Services\SequenceAllocator;
use App\Domain\Projects\Opportunities\Verbs\Events\AssetAllocated;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * Allocates a single serialised asset to an opportunity line item via the
 * AssetAllocated genesis event, allocating the replay-stable assignment id, firing
 * the event, and committing it with its projection atomically.
 */
class AllocateAsset
{
    use CommitsVerbsEvents;

    public function __invoke(OpportunityItem $item, AllocateAssetData $data): OpportunityItemAssetData
    {
        Gate::authorize('update', $item->opportunity);
        Gate::authorize('access', StockLevel::query()->findOrFail($data->stock_level_id));

        $assignmentId = null;
        $allocatedAt = Carbon::now('UTC')->toIso8601String();

        $this->commitVerbs(function () use ($item, $data, $allocatedAt, &$assignmentId): void {
            $stockLevel = StockLevel::query()->findOrFail($data->stock_level_id);
            $productId = app(OpportunityItemDemandResolver::class)->resolveContext($item)['product_id'];
            if ($productId !== null) {
                app(ProductStoreCapacityLock::class)->acquire($productId, $stockLevel->store_id);
            }

            // Allocate the replay-stable small PK and bake it into the event so a
            // truncate + Verbs::replay() rebuild reproduces the identical id.
            $assignmentId = app(SequenceAllocator::class)->next('opportunity_item_assets');

            AssetAllocated::fire(
                assignment_id: $assignmentId,
                opportunity_item_id: $item->id,
                stock_level_id: $data->stock_level_id,
                allocated_at: $allocatedAt,
            );
        });

        $asset = OpportunityItemAsset::query()->whereKey($assignmentId)->with('stockLevel')->firstOrFail();

        return OpportunityItemAssetData::fromModel($asset);
    }
}
