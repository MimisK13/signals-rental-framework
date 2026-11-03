<?php

declare(strict_types=1);

namespace App\Domain\Projects\Opportunities\Actions;

use App\Concerns\CommitsVerbsEvents;
use App\Domain\Projects\Opportunities\Data\OpportunityItemAssetData;
use App\Domain\Projects\Opportunities\Models\OpportunityItemAsset;
use App\Domain\Projects\Opportunities\Verbs\Events\AssetPrepared;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * Marks an allocated asset as prepared via the AssetPrepared event.
 */
class PrepareAsset
{
    use CommitsVerbsEvents;

    public function __invoke(OpportunityItemAsset $asset): OpportunityItemAssetData
    {
        Gate::authorize('update', $asset->item->opportunity);

        $stateId = $asset->state_id;
        $preparedAt = Carbon::now('UTC')->toIso8601String();

        $this->commitVerbs(function () use ($stateId, $preparedAt): void {
            AssetPrepared::fire(state_id: $stateId, prepared_at: $preparedAt);
        });

        return OpportunityItemAssetData::fromModel($asset->fresh(['stockLevel']));
    }
}
