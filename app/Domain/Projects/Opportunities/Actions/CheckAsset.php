<?php

declare(strict_types=1);

namespace App\Domain\Projects\Opportunities\Actions;

use App\Concerns\CommitsVerbsEvents;
use App\Domain\Projects\Opportunities\Data\CheckAssetData;
use App\Domain\Projects\Opportunities\Data\OpportunityItemAssetData;
use App\Domain\Projects\Opportunities\Models\OpportunityItemAsset;
use App\Domain\Projects\Opportunities\Verbs\Events\AssetChecked;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * Records the condition assessment for a returned asset via the AssetChecked
 * event (the RMS `finalise_check_in` action).
 */
class CheckAsset
{
    use CommitsVerbsEvents;

    public function __invoke(OpportunityItemAsset $asset, CheckAssetData $data): OpportunityItemAssetData
    {
        Gate::authorize('update', $asset->item->opportunity);

        $checkedAt = $data->checked_at ?? Carbon::now('UTC')->toIso8601String();

        $this->commitVerbs(function () use ($asset, $data, $checkedAt): void {
            $locked = OpportunityItemAsset::query()
                ->whereKey($asset->id)
                ->lockForUpdate()
                ->firstOrFail();

            AssetChecked::fire(
                state_id: $locked->state_id,
                condition: $data->condition,
                checked_by: $data->checked_by,
                damage_notes: $data->damage_notes,
                checked_at: $checkedAt,
            );
        });

        return OpportunityItemAssetData::fromModel($asset->fresh(['stockLevel']) ?? $asset);
    }
}
