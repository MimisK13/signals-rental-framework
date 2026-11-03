<?php

declare(strict_types=1);

namespace App\Domain\Projects\Opportunities\Actions;

use App\Concerns\CommitsVerbsEvents;
use App\Domain\Projects\Opportunities\Data\OpportunityItemAssetData;
use App\Domain\Projects\Opportunities\Data\SetAssetContainerData;
use App\Domain\Projects\Opportunities\Models\OpportunityItemAsset;
use App\Domain\Projects\Opportunities\Verbs\Events\AssetContainerSet;
use Illuminate\Support\Facades\Gate;

/**
 * Nests an asset assignment inside a container stock level via the
 * AssetContainerSet event.
 */
class SetAssetContainer
{
    use CommitsVerbsEvents;

    public function __invoke(OpportunityItemAsset $asset, SetAssetContainerData $data): OpportunityItemAssetData
    {
        Gate::authorize('update', $asset->item->opportunity);

        $stateId = $asset->state_id;

        $this->commitVerbs(function () use ($stateId, $data): void {
            AssetContainerSet::fire(
                state_id: $stateId,
                container_stock_level_id: $data->container_stock_level_id,
            );
        });

        return OpportunityItemAssetData::fromModel($asset->fresh(['stockLevel']));
    }
}
