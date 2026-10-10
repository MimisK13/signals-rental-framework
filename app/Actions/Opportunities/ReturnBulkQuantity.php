<?php

namespace App\Actions\Opportunities;

use App\Actions\Opportunities\Concerns\RebuildsAvailabilitySnapshots;
use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\BulkReturnData;
use App\Data\Opportunities\OpportunityItemData;
use App\Models\OpportunityItem;
use App\Services\Availability\OpportunityItemDemandResolver;
use App\Services\Availability\ProductStoreCapacityLock;
use App\Verbs\Events\Opportunities\BulkQuantityReturned;
use Illuminate\Support\Facades\Gate;

/**
 * Records a (partial) return of a bulk line via the BulkQuantityReturned event,
 * then rebuilds the line's product/store snapshots so the freed quantity is
 * re-materialised across the horizon.
 */
class ReturnBulkQuantity
{
    use CommitsVerbsEvents, RebuildsAvailabilitySnapshots;

    public function __invoke(OpportunityItem $item, BulkReturnData $data): OpportunityItemData
    {
        Gate::authorize('update', $item->opportunity);

        $this->commitVerbs(function () use ($item, $data): void {
            $lockedItem = OpportunityItem::query()
                ->whereKey($item->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $context = app(OpportunityItemDemandResolver::class)->resolveContext($lockedItem);
            if ($context['product_id'] !== null && $context['store_id'] !== null) {
                app(ProductStoreCapacityLock::class)->acquire($context['product_id'], $context['store_id']);
            }

            BulkQuantityReturned::fire(
                opportunity_item_id: $lockedItem->state_id,
                quantity: $data->quantity,
                received_by: $data->received_by,
                condition: $data->condition,
            );
        });

        $fresh = $item->fresh(['opportunity']) ?? $item;

        $this->rebuildSnapshotsForItems([$fresh]);

        return OpportunityItemData::fromModel($fresh);
    }
}
