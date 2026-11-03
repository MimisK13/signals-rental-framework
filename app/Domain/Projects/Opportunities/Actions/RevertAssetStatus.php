<?php

declare(strict_types=1);

namespace App\Domain\Projects\Opportunities\Actions;

use App\Concerns\CommitsVerbsEvents;
use App\Domain\Projects\Opportunities\Actions\Concerns\RebuildsAvailabilitySnapshots;
use App\Domain\Projects\Opportunities\Data\OpportunityItemAssetData;
use App\Domain\Projects\Opportunities\Data\RevertAssetStatusData;
use App\Domain\Projects\Opportunities\Models\OpportunityItemAsset;
use App\Domain\Projects\Opportunities\Verbs\Events\AssetStatusReverted;
use Illuminate\Support\Facades\Gate;

/**
 * Reverts a serialised asset to an earlier dispatch/return status to correct a
 * mistaken scan, via the AssetStatusReverted event.
 */
class RevertAssetStatus
{
    use CommitsVerbsEvents, RebuildsAvailabilitySnapshots;

    public function __invoke(OpportunityItemAsset $asset, RevertAssetStatusData $data): OpportunityItemAssetData
    {
        Gate::authorize('update', $asset->item->opportunity);

        $this->commitVerbs(function () use ($asset, $data): void {
            AssetStatusReverted::fire(
                state_id: $asset->state_id,
                revert_to: $data->revert_to,
                reason: $data->reason,
            );
        });

        $fresh = $asset->fresh(['item.opportunity', 'stockLevel']) ?? $asset;

        if ($fresh->item !== null) {
            $this->rebuildSnapshotsForItems([$fresh->item]);
        }

        return OpportunityItemAssetData::fromModel($fresh);
    }
}
