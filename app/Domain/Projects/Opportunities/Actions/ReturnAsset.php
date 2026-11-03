<?php

declare(strict_types=1);

namespace App\Domain\Projects\Opportunities\Actions;

use App\Concerns\CommitsVerbsEvents;
use App\Domain\Projects\Opportunities\Actions\Concerns\RebuildsAvailabilitySnapshots;
use App\Domain\Projects\Opportunities\Data\OpportunityItemAssetData;
use App\Domain\Projects\Opportunities\Data\ReturnAssetData;
use App\Domain\Projects\Opportunities\Models\OpportunityItemAsset;
use App\Domain\Stock\Models\StockLevel;
use App\Domain\Warehouse\Containers\Services\KitContainerReturnCompletion;
use App\Domain\Projects\Opportunities\Verbs\Events\AssetReturned;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * Checks a single serialised asset back into the warehouse via the AssetReturned
 * event (the RMS `check_in` action). Dispatches a snapshot rebuild for the line's
 * product/store so a contracted (early/overdue) return re-materialises the freed
 * far slots.
 */
class ReturnAsset
{
    use CommitsVerbsEvents, RebuildsAvailabilitySnapshots;

    public function __invoke(OpportunityItemAsset $asset, ReturnAssetData $data): OpportunityItemAssetData
    {
        Gate::authorize('update', $asset->item->opportunity);
        if ($data->return_store_id !== null) {
            Gate::authorize('access', new StockLevel(['store_id' => $data->return_store_id]));
        }

        $returnedAt = $data->returned_at ?? Carbon::now('UTC')->toIso8601String();

        $this->commitVerbs(function () use ($asset, $data, $returnedAt): void {
            $locked = OpportunityItemAsset::query()
                ->whereKey($asset->id)
                ->lockForUpdate()
                ->firstOrFail();

            AssetReturned::fire(
                state_id: $locked->state_id,
                received_by: $data->received_by,
                return_store_id: $data->return_store_id,
                returned_at: $returnedAt,
            );
        });

        $fresh = $asset->fresh(['item.opportunity', 'stockLevel']) ?? $asset;

        if ($fresh->item !== null) {
            $this->rebuildSnapshotsForItems([$fresh->item]);
        }

        $container = app(KitContainerReturnCompletion::class)->containerForAssignment($fresh);

        if ($container !== null) {
            app(KitContainerReturnCompletion::class)->maybeCompleteIndividualReturn($container);
        }

        return OpportunityItemAssetData::fromModel($fresh);
    }
}
