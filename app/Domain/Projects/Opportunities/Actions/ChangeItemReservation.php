<?php

declare(strict_types=1);

namespace App\Domain\Projects\Opportunities\Actions;

use App\Concerns\CommitsVerbsEvents;
use App\Domain\Availability\Services\OpportunityItemDemandResolver;
use App\Domain\Availability\Services\OwnedCapacityReader;
use App\Domain\Availability\Services\ProductStoreCapacityLock;
use App\Domain\Projects\Opportunities\Data\ChangeItemReservationData;
use App\Domain\Projects\Opportunities\Data\OpportunityData;
use App\Domain\Projects\Opportunities\Models\OpportunityItem;
use App\Domain\Projects\Opportunities\Verbs\Events\ItemReservationChanged;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Establishes or changes explicit owned-capacity reservation intent.
 *
 * The event resynchronises the existing Demand projection through the single
 * OpportunityItemDemandResolver claim rule. Availability remains unaware of
 * reservation modes.
 */
class ChangeItemReservation
{
    use CommitsVerbsEvents;

    public function __invoke(OpportunityItem $item, ChangeItemReservationData $data): OpportunityData
    {
        Gate::authorize('opportunities.edit');

        $opportunity = $item->opportunity()->firstOrFail();

        $this->commitVerbs(function () use ($item, $data): void {
            $item->refresh();
            $resolver = app(OpportunityItemDemandResolver::class);
            $context = $resolver->resolveContext($item);

            $phase = app(OpportunityItemDemandResolver::class)->resolvePhase($item);

            if ($phase->isActive() && $context['product_id'] !== null && $context['store_id'] !== null) {
                app(ProductStoreCapacityLock::class)->acquire(
                    $context['product_id'],
                    $context['store_id'],
                );

                // The projection may have changed while waiting for the
                // product/store lock. Re-read it before computing the delta so
                // same-line retries cannot validate against stale intent.
                $item->refresh();
                $context = $resolver->resolveContext($item);
                if ($context['product_id'] !== null && $context['store_id'] !== null) {
                    app(ProductStoreCapacityLock::class)->acquire(
                        $context['product_id'],
                        $context['store_id'],
                    );
                }

                $oldClaim = $resolver->effectiveClaimQuantity($item);
                $newClaim = $resolver->effectiveClaimQuantityFor($item, $data->reserved_quantity);
                $additionalClaim = $newClaim - $oldClaim;

                if ($additionalClaim > 0) {
                    $available = app(OwnedCapacityReader::class)->availableForItem($item);

                    if ($additionalClaim > $available) {
                        throw ValidationException::withMessages([
                            'reserved_quantity' => [
                                sprintf('Only %d additional owned unit(s) are available for this planning period and Store.', $available),
                            ],
                        ]);
                    }
                }
            }

            ItemReservationChanged::fire(
                opportunity_item_id: $item->state_id,
                reserved_quantity: $data->reserved_quantity,
            );
        });

        return OpportunityData::fromModel($opportunity->fresh(['items']));
    }
}
