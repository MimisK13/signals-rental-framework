<?php

declare(strict_types=1);

namespace App\Domain\Projects\Opportunities\Actions;

use App\Concerns\CommitsVerbsEvents;
use App\Domain\Assets\Enums\AssetCondition;
use App\Domain\Projects\Opportunities\Actions\Concerns\RebuildsAvailabilitySnapshots;
use App\Domain\Projects\Opportunities\Actions\Concerns\ResolvesOpportunityAssets;
use App\Domain\Projects\Opportunities\Data\OpportunityData;
use App\Domain\Projects\Opportunities\Data\QuickCheckInData;
use App\Domain\Projects\Opportunities\Enums\AssetAssignmentStatus;
use App\Domain\Projects\Opportunities\Models\Opportunity;
use App\Domain\Projects\Opportunities\Services\AutoPromotionContext;
use App\Domain\Projects\Opportunities\Verbs\Events\AssetChecked;
use App\Domain\Projects\Opportunities\Verbs\Events\AssetReturned;
use App\Domain\Projects\Opportunities\Verbs\Events\Concerns\PromotesOpportunityStatus;
use App\Domain\Stock\Models\StockLevel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * Checks several serialised assets back into one opportunity in a SINGLE atomic
 * Verbs commit (the RMS `quick_check_in` action, opportunity-lifecycle.md §11.1).
 *
 * Each asset fires an {@see AssetReturned}; when `finalise` is set it is immediately
 * followed by an {@see AssetChecked} (condition Good) so the check-in queue is
 * cleared in one pass (the RMS `finalise_check_in`). Any failure rolls back the
 * whole batch.
 *
 * Auto-promotion consistency mirrors {@see QuickBookOut}: a single final
 * authoritative promotion is fired with the whole batch overlaid so the order lands
 * on the correct aggregate (Returned, or Checked when finalising) in one step.
 * A snapshot rebuild is dispatched once per distinct product/store touched.
 */
class QuickCheckIn
{
    use CommitsVerbsEvents, PromotesOpportunityStatus, RebuildsAvailabilitySnapshots, ResolvesOpportunityAssets;

    public function __invoke(Opportunity $opportunity, QuickCheckInData $data): OpportunityData
    {
        Gate::authorize('update', $opportunity);
        if ($data->return_store_id !== null) {
            Gate::authorize('access', new StockLevel(['store_id' => $data->return_store_id]));
        }

        $returnedAt = $data->returned_at ?? Carbon::now('UTC')->toIso8601String();

        $this->commitVerbs(function () use ($opportunity, $data, $returnedAt): void {
            $overlay = app(AutoPromotionContext::class)->suppress(function () use ($opportunity, $data, $returnedAt): array {
                $overlay = [];

                foreach ($data->asset_ids as $assetId) {
                    $asset = $this->assetForOpportunity((int) $assetId, $opportunity);

                    AssetReturned::fire(
                        state_id: $asset->state_id,
                        received_by: $data->received_by,
                        return_store_id: $data->return_store_id,
                        returned_at: $returnedAt,
                    );

                    if ($data->finalise) {
                        AssetChecked::fire(
                            state_id: $asset->state_id,
                            condition: AssetCondition::Good->value,
                            checked_by: $data->received_by,
                            checked_at: $returnedAt,
                        );
                    }

                    $overlay[$asset->id] = $data->finalise
                        ? AssetAssignmentStatus::Finalised
                        : AssetAssignmentStatus::CheckedIn;
                }

                return $overlay;
            });

            $this->promoteOpportunityFromItems(
                $opportunity->fresh(),
                ['assignment_statuses' => $overlay],
            );
        });

        $fresh = $opportunity->fresh(['items', 'items.assets']) ?? $opportunity;

        $this->rebuildSnapshotsForItems($fresh->items);

        return OpportunityData::fromModel($fresh);
    }
}
