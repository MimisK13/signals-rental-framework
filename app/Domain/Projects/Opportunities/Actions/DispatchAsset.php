<?php

declare(strict_types=1);

namespace App\Domain\Projects\Opportunities\Actions;

use App\Concerns\CommitsVerbsEvents;
use App\Domain\Availability\Shortages\Enums\ShortageDispatchPolicy;
use App\Domain\Availability\Shortages\Services\DispatchShortageGate;
use App\Domain\Availability\Shortages\ValueObjects\DispatchGateResult;
use App\Domain\Projects\Opportunities\Data\DispatchAssetData;
use App\Domain\Projects\Opportunities\Data\OpportunityItemAssetData;
use App\Domain\Projects\Opportunities\Models\OpportunityItemAsset;
use App\Domain\Projects\Opportunities\Verbs\Events\AssetDispatched;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * Books a single serialised asset out of the warehouse via the AssetDispatched
 * event (the RMS `book_out` action).
 *
 * Before firing, the {@see DispatchShortageGate} enforces the store's
 * {@see ShortageDispatchPolicy} on the asset's line (§7.4): Block →
 * 422; WarnPartial → proceed and expose held-item metadata via {@see $gateResult};
 * AllowPartial → proceed silently. The gate runs inside the same atomic commit, so
 * a Block leaves nothing dispatched.
 */
class DispatchAsset
{
    use CommitsVerbsEvents;

    /**
     * The dispatch-gate outcome from the last invocation — the controller reads it
     * to surface held-item metadata on a WarnPartial dispatch. Null until invoked.
     */
    public ?DispatchGateResult $gateResult = null;

    public function __invoke(OpportunityItemAsset $asset, DispatchAssetData $data): OpportunityItemAssetData
    {
        Gate::authorize('update', $asset->item->opportunity);

        $dispatchedAt = $data->dispatched_at ?? Carbon::now('UTC')->toIso8601String();

        $this->commitVerbs(function () use ($asset, $data, $dispatchedAt): void {
            $locked = OpportunityItemAsset::query()
                ->whereKey($asset->id)
                ->lockForUpdate()
                ->firstOrFail();

            $item = $locked->item()->first();

            if ($item !== null) {
                // §7.4 dispatch gate — Block throws (rolling back the commit);
                // WarnPartial/AllowPartial proceed (held-item meta captured below).
                $this->gateResult = app(DispatchShortageGate::class)->enforceForItem($item);
            }

            AssetDispatched::fire(
                state_id: $locked->state_id,
                dispatched_by: $data->dispatched_by,
                vehicle_id: $data->vehicle_id,
                notes: $data->notes,
                dispatched_at: $dispatchedAt,
            );
        });

        return OpportunityItemAssetData::fromModel(
            $asset->fresh(['stockLevel']) ?? $asset,
        );
    }
}
