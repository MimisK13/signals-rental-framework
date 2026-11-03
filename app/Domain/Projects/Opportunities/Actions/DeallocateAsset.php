<?php

declare(strict_types=1);

namespace App\Domain\Projects\Opportunities\Actions;

use App\Concerns\CommitsVerbsEvents;
use App\Domain\Availability\Services\ProductStoreCapacityLock;
use App\Domain\Projects\Opportunities\Models\OpportunityItemAsset;
use App\Domain\Projects\Opportunities\Verbs\Events\AssetDeallocated;
use Illuminate\Support\Facades\Gate;

/**
 * Releases an allocated/prepared asset from its line item via the AssetDeallocated
 * event. The projection row is removed, so there is nothing to return.
 */
class DeallocateAsset
{
    use CommitsVerbsEvents;

    public function __invoke(OpportunityItemAsset $asset, ?string $reason = null): void
    {
        Gate::authorize('update', $asset->item->opportunity);

        $stateId = $asset->state_id;

        $this->commitVerbs(function () use ($stateId, $reason): void {
            $asset = OpportunityItemAsset::query()->with('stockLevel')->where('state_id', $stateId)->firstOrFail();
            app(ProductStoreCapacityLock::class)->acquire(
                (int) $asset->stockLevel->product_id,
                (int) $asset->stockLevel->store_id,
            );

            AssetDeallocated::fire(
                state_id: $stateId,
                reason: $reason,
            );
        });
    }
}
