<?php

declare(strict_types=1);

namespace App\Domain\Projects\Opportunities\Actions;

use App\Concerns\CommitsVerbsEvents;
use App\Domain\Projects\Opportunities\Data\OpportunityItemAssetData;
use App\Domain\Projects\Opportunities\Models\OpportunityItemAsset;
use App\Domain\Projects\Opportunities\Verbs\Events\AssetPreparationReverted;
use Illuminate\Support\Facades\Gate;

/**
 * Reverts a prepared asset back to Allocated via the AssetPreparationReverted
 * event.
 */
class RevertAssetPreparation
{
    use CommitsVerbsEvents;

    public function __invoke(OpportunityItemAsset $asset): OpportunityItemAssetData
    {
        Gate::authorize('update', $asset->item->opportunity);

        $stateId = $asset->state_id;

        $this->commitVerbs(function () use ($stateId): void {
            AssetPreparationReverted::fire(state_id: $stateId);
        });

        return OpportunityItemAssetData::fromModel($asset->fresh(['stockLevel']));
    }
}
