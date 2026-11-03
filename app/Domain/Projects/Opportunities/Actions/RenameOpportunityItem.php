<?php

declare(strict_types=1);

namespace App\Domain\Projects\Opportunities\Actions;

use App\Concerns\CommitsVerbsEvents;
use App\Domain\Projects\Opportunities\Data\OpportunityItemData;
use App\Domain\Projects\Opportunities\Data\RenameOpportunityItemData;
use App\Domain\Projects\Opportunities\Models\OpportunityItem;
use App\Domain\Projects\Opportunities\Verbs\Events\ItemRenamed;
use Illuminate\Support\Facades\Gate;

/**
 * Renames a line item — a group header or a product/accessory/service line —
 * via the ItemRenamed event.
 */
class RenameOpportunityItem
{
    use CommitsVerbsEvents;

    public function __invoke(OpportunityItem $item, RenameOpportunityItemData $data): OpportunityItemData
    {
        Gate::authorize('opportunities.edit');

        $this->commitVerbs(function () use ($item, $data): void {
            ItemRenamed::fire(
                opportunity_item_id: $item->state_id,
                name: $data->name,
            );
        });

        return OpportunityItemData::fromModel($item->fresh());
    }
}
