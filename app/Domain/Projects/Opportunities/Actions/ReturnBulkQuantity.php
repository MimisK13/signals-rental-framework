<?php

declare(strict_types=1);

namespace App\Domain\Projects\Opportunities\Actions;

use App\Concerns\CommitsVerbsEvents;
use App\Domain\Availability\Services\OpportunityItemDemandResolver;
use App\Domain\Availability\Services\ProductStoreCapacityLock;
use App\Domain\Projects\Opportunities\Actions\Concerns\RebuildsAvailabilitySnapshots;
use App\Domain\Projects\Opportunities\Data\BulkReturnData;
use App\Domain\Projects\Opportunities\Data\OpportunityItemData;
use App\Domain\Projects\Opportunities\Models\OpportunityItem;
use App\Domain\Projects\Opportunities\Verbs\Events\BulkQuantityReturned;
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
