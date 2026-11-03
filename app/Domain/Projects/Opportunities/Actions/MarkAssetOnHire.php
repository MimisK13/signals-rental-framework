<?php

declare(strict_types=1);

namespace App\Domain\Projects\Opportunities\Actions;

use App\Concerns\CommitsVerbsEvents;
use App\Domain\Projects\Opportunities\Data\OpportunityItemAssetData;
use App\Domain\Projects\Opportunities\Models\OpportunityItemAsset;
use App\Domain\Projects\Opportunities\Verbs\Events\AssetOnHire;
use Illuminate\Support\Facades\Gate;

/**
 * Confirms a dispatched asset is now on hire with the client via the AssetOnHire
 * event.
 */
class MarkAssetOnHire
{
    use CommitsVerbsEvents;

    public function __invoke(OpportunityItemAsset $asset): OpportunityItemAssetData
    {
        Gate::authorize('update', $asset->item->opportunity);

        $this->commitVerbs(function () use ($asset): void {
            AssetOnHire::fire(state_id: $asset->state_id);
        });

        return OpportunityItemAssetData::fromModel($asset->fresh(['stockLevel']) ?? $asset);
    }
}
