<?php

namespace App\Actions\Opportunities;

use App\Actions\Opportunities\Concerns\FormatsOpportunityDates;
use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\ChangeItemDatesData;
use App\Data\Opportunities\OpportunityData;
use App\Models\OpportunityItem;
use App\Services\Availability\OpportunityItemDemandResolver;
use App\Services\Availability\ProductStoreCapacityLock;
use App\Services\Shortages\ItemShortageProbe;
use App\Verbs\Events\Opportunities\ItemDatesChanged;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelData\Optional;

/**
 * Changes a line item's per-item hire window via the ItemDatesChanged event.
 *
 * After the event commits, the {@see ItemShortageProbe} rechecks the line for a
 * shortage over its new window (shortage-resolution-sub-hires.md §2.4 "On date
 * changes"), emitting `shortage.detected`/`shortage.cleared` as appropriate. The
 * probe never blocks the edit and is skipped during replay.
 */
class ChangeItemDates
{
    use CommitsVerbsEvents, FormatsOpportunityDates;

    public function __invoke(OpportunityItem $item, ChangeItemDatesData $data): OpportunityData
    {
        Gate::authorize('opportunities.edit');

        $opportunity = $item->opportunity()->firstOrFail();

        // Resolve the effective window NOW (requested dates ?? opportunity dates ??
        // a single captured fire-time now()) and bake CONCRETE dates into the event
        // so the totals engine never calls now() in handle() — keeping replay totals
        // identical for a dateless rate-priced line.
        $now = Carbon::now('UTC');
        $startsAt = $data->starts_at
            ?? $this->toIso($opportunity->starts_at)
            ?? $now->toIso8601String();
        $endsAt = $data->ends_at
            ?? $this->toIso($opportunity->ends_at)
            ?? Carbon::parse($startsAt)->copy()->addDay()->toIso8601String();

        $planningStartsAt = $data->planning_starts_at instanceof Optional
            ? $this->toIso($item->planning_starts_at)
            : ($data->planning_starts_at !== null ? Carbon::parse($data->planning_starts_at)->toIso8601String() : null);
        $planningEndsAt = $data->planning_ends_at instanceof Optional
            ? $this->toIso($item->planning_ends_at)
            : ($data->planning_ends_at !== null ? Carbon::parse($data->planning_ends_at)->toIso8601String() : null);

        $planningPeriodProvided = ! $data->planning_starts_at instanceof Optional
            || ! $data->planning_ends_at instanceof Optional;

        $this->commitVerbs(function () use ($item, $startsAt, $endsAt, $planningStartsAt, $planningEndsAt, $planningPeriodProvided): void {
            $context = app(OpportunityItemDemandResolver::class)->resolveContext($item->refresh());
            if ($context['product_id'] !== null && $context['store_id'] !== null) {
                app(ProductStoreCapacityLock::class)->acquire($context['product_id'], $context['store_id']);
            }

            ItemDatesChanged::fire(
                opportunity_item_id: $item->state_id,
                starts_at: $startsAt,
                ends_at: $endsAt,
                planning_starts_at: $planningStartsAt,
                planning_ends_at: $planningEndsAt,
                planning_period_provided: $planningPeriodProvided,
            );
        });

        // Inline re-detection (§2.4) over the line's new window.
        app(ItemShortageProbe::class)->probe($item->refresh());

        return OpportunityData::fromModel($opportunity->fresh(['items']));
    }
}
