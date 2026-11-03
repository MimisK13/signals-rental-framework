<?php

declare(strict_types=1);

namespace App\Domain\Projects\Opportunities\Actions;

use App\Concerns\CommitsVerbsEvents;
use App\Domain\Projects\Opportunities\Data\BulkAdjustData;
use App\Domain\Projects\Opportunities\Data\OpportunityItemData;
use App\Domain\Projects\Opportunities\Models\OpportunityItem;
use App\Domain\Projects\Opportunities\Verbs\Events\BulkQuantityAdjusted;
use Illuminate\Support\Facades\Gate;

/**
 * Adjusts the requested quantity of a bulk line mid-cycle via the
 * BulkQuantityAdjusted event.
 */
class AdjustBulkQuantity
{
    use CommitsVerbsEvents;

    public function __invoke(OpportunityItem $item, BulkAdjustData $data): OpportunityItemData
    {
        Gate::authorize('opportunities.edit');

        $this->commitVerbs(function () use ($item, $data): void {
            BulkQuantityAdjusted::fire(
                opportunity_item_id: $item->state_id,
                new_quantity: $data->new_quantity,
                reason: $data->reason,
            );
        });

        return OpportunityItemData::fromModel($item->fresh() ?? $item);
    }
}
