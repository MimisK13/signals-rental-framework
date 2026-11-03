<?php

declare(strict_types=1);

namespace App\Domain\Projects\Opportunities\Actions;

use App\Concerns\CommitsVerbsEvents;
use App\Domain\Availability\Services\OpportunityItemDemandResolver;
use App\Domain\Availability\Services\ProductStoreCapacityLock;
use App\Domain\Availability\Shortages\Enums\ShortageDispatchPolicy;
use App\Domain\Availability\Shortages\Services\DispatchShortageGate;
use App\Domain\Availability\Shortages\ValueObjects\DispatchGateResult;
use App\Domain\Projects\Opportunities\Data\BulkDispatchData;
use App\Domain\Projects\Opportunities\Data\OpportunityItemData;
use App\Domain\Projects\Opportunities\Models\OpportunityItem;
use App\Domain\Projects\Opportunities\Verbs\Events\BulkQuantityDispatched;
use Illuminate\Support\Facades\Gate;

/**
 * Records a (partial) dispatch of a bulk line via the BulkQuantityDispatched
 * event.
 *
 * Before firing, the {@see DispatchShortageGate} enforces the store's
 * {@see ShortageDispatchPolicy} on the line (§7.4): Block → 422;
 * WarnPartial → proceed and expose held-item metadata via {@see $gateResult};
 * AllowPartial → proceed silently. The gate runs inside the atomic commit.
 */
class DispatchBulkQuantity
{
    use CommitsVerbsEvents;

    /**
     * The dispatch-gate outcome from the last invocation — the controller reads it
     * to surface held-item metadata on a WarnPartial dispatch. Null until invoked.
     */
    public ?DispatchGateResult $gateResult = null;

    public function __invoke(OpportunityItem $item, BulkDispatchData $data): OpportunityItemData
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

            // §7.4 dispatch gate — Block throws (rolling back); Warn/Allow proceed.
            $this->gateResult = app(DispatchShortageGate::class)->enforceForItem($lockedItem);

            $quantity = $this->gateResult->clampBulkDispatchQuantity($lockedItem, $data->quantity);

            BulkQuantityDispatched::fire(
                opportunity_item_id: $lockedItem->state_id,
                quantity: $quantity,
                dispatched_by: $data->dispatched_by,
            );
        });

        return OpportunityItemData::fromModel($item->fresh() ?? $item);
    }
}
