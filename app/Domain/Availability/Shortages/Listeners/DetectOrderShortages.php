<?php

declare(strict_types=1);

namespace App\Domain\Availability\Shortages\Listeners;

use App\Domain\Availability\Events\AvailabilityChanged;
use App\Domain\Availability\Models\Demand;
use App\Domain\Availability\Shortages\Services\ItemShortageProbe;
use App\Domain\Availability\Shortages\Services\PipelineShortageEmitter;
use App\Domain\Availability\Shortages\Services\ShortageDetector;
use App\Domain\Availability\Shortages\Services\ShortageEventRecorder;
use App\Domain\Availability\Shortages\ValueObjects\ShortageCollection;
use App\Domain\Projects\Opportunities\Enums\OpportunityState;
use App\Domain\Projects\Opportunities\Models\OpportunityItem;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Thunk\Verbs\Facades\Verbs;

/**
 * Proactive shortage monitoring (shortage-resolution-sub-hires.md §2.4 "On
 * availability change events").
 *
 * Listens for {@see AvailabilityChanged} (broadcast after a recalc commits) and
 * cross-references the affected product/store against CONFIRMED-order demands. For
 * each confirmed line item drawing on that product/store it re-runs the
 * {@see ShortageDetector}; when a NEW shortage now exists it fires
 * `shortage.detected` at OPPORTUNITY-ITEM granularity — surfacing the problem on
 * the specific order before a user hits it at dispatch.
 *
 * This is deliberately scoped to the opportunity-item level so it does NOT
 * duplicate the product/store-level events the
 * {@see PipelineShortageEmitter} already wrote during the
 * same recalc (different `source_type`, no double-fire). It also dedupes against
 * its own prior detections via the open-shortage check so a series of recalcs for
 * the same standing shortage emits `shortage.detected` once, not on every recalc.
 *
 * Replay-safe: {@see AvailabilityChanged} is never dispatched during a Verbs
 * replay, and this listener additionally short-circuits on replay as a guard.
 */
class DetectOrderShortages implements ShouldQueue
{
    /** Queue alongside the availability recompute work. */
    public function viaQueue(): string
    {
        return (string) config('availability.recalc.queue', 'availability');
    }

    public function __construct(
        private readonly ShortageDetector $detector,
        private readonly ShortageEventRecorder $events,
        private readonly ItemShortageProbe $probe,
    ) {}

    public function handle(AvailabilityChanged $event): void
    {
        if (Verbs::isReplaying()) {
            return;
        }

        $itemIds = $this->confirmedOrderItemIds($event->productId, $event->storeId);

        if ($itemIds === []) {
            return;
        }

        OpportunityItem::query()
            ->whereIn('id', $itemIds)
            // Narrow to lines whose parent opportunity is an Order at the DB level
            // rather than loading every line and filtering in PHP.
            ->whereHas('opportunity', fn (Builder $query) => $query->where('state', OpportunityState::Order->value))
            ->with('opportunity')
            ->get()
            ->each(function (OpportunityItem $item): void {
                $opportunity = $item->opportunity;

                // The opportunity is guaranteed present and an Order by the
                // whereHas above; this guards only against a row vanishing between
                // the filtered read and the eager load.
                if ($opportunity === null) {
                    return;
                }

                $shortage = $this->detector->forItem($item, $opportunity);

                // Only emit when there is an unresolved shortage AND this line does
                // not already have an open (uncleared) detection — so a standing
                // shortage is announced once, not on every recalc.
                if ($shortage === null || ! $shortage->isUnresolved() || $this->probe->hasOpenShortageForItemId($item->id)) {
                    return;
                }

                $this->events->detected(new ShortageCollection([$shortage]));
            });
    }

    /**
     * The opportunity-item ids with active demands on the changed product/store —
     * the lines whose availability this recalc may have affected.
     *
     * @return list<int>
     */
    private function confirmedOrderItemIds(int $productId, int $storeId): array
    {
        return Demand::query()
            ->where('product_id', $productId)
            ->where('store_id', $storeId)
            ->where('source_type', 'opportunity_item')
            ->active()
            ->distinct()
            ->pluck('source_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }
}
