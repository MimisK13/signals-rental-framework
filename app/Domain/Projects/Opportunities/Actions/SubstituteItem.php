<?php

declare(strict_types=1);

namespace App\Domain\Projects\Opportunities\Actions;

use App\Concerns\CommitsVerbsEvents;
use App\Domain\Projects\Opportunities\Data\OpportunityData;
use App\Domain\Projects\Opportunities\Data\SubstituteItemData;
use App\Domain\Projects\Opportunities\Models\OpportunityItem;
use App\Domain\Projects\Opportunities\Verbs\Events\ItemSubstituted;
use Illuminate\Support\Facades\Gate;

/**
 * Substitutes the catalogue item a line refers to via the ItemSubstituted event.
 */
class SubstituteItem
{
    use CommitsVerbsEvents;

    public function __invoke(OpportunityItem $item, SubstituteItemData $data): OpportunityData
    {
        Gate::authorize('opportunities.edit');

        $opportunity = $item->opportunity()->firstOrFail();

        $this->commitVerbs(function () use ($item, $data): void {
            ItemSubstituted::fire(
                opportunity_item_id: $item->state_id,
                item_id: $data->item_id,
                item_type: $data->itemable_type,
                name: $data->name,
            );
        });

        return OpportunityData::fromModel($opportunity->fresh(['items']));
    }
}
