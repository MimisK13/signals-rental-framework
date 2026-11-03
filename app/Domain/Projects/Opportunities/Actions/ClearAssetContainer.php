<?php

declare(strict_types=1);

namespace App\Domain\Projects\Opportunities\Actions;

use App\Concerns\CommitsVerbsEvents;
use App\Domain\Projects\Opportunities\Data\OpportunityItemAssetData;
use App\Domain\Projects\Opportunities\Models\OpportunityItemAsset;
use App\Domain\Projects\Opportunities\Verbs\Events\AssetContainerCleared;
use Illuminate\Support\Facades\Gate;

/**
 * Removes an asset assignment from its container via the AssetContainerCleared
 * event.
 */
class ClearAssetContainer
{
    use CommitsVerbsEvents;

    public function __invoke(OpportunityItemAsset $asset): OpportunityItemAssetData
    {
        Gate::authorize('update', $asset->item->opportunity);

        $stateId = $asset->state_id;

        $this->commitVerbs(function () use ($stateId): void {
            AssetContainerCleared::fire(state_id: $stateId);
        });

        return OpportunityItemAssetData::fromModel($asset->fresh(['stockLevel']));
    }
}
