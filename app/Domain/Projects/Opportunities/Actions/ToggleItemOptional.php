<?php

declare(strict_types=1);

namespace App\Domain\Projects\Opportunities\Actions;

use App\Concerns\CommitsVerbsEvents;
use App\Domain\Projects\Opportunities\Data\OpportunityData;
use App\Domain\Projects\Opportunities\Data\ToggleItemOptionalData;
use App\Domain\Projects\Opportunities\Models\OpportunityItem;
use App\Domain\Projects\Opportunities\Verbs\Events\ItemOptionalToggled;
use Illuminate\Support\Facades\Gate;

/**
 * Toggles whether a line item is optional via the ItemOptionalToggled event.
 */
class ToggleItemOptional
{
    use CommitsVerbsEvents;

    public function __invoke(OpportunityItem $item, ToggleItemOptionalData $data): OpportunityData
    {
        Gate::authorize('opportunities.edit');

        $opportunity = $item->opportunity()->firstOrFail();

        $this->commitVerbs(function () use ($item, $data): void {
            ItemOptionalToggled::fire(
                opportunity_item_id: $item->state_id,
                is_optional: $data->is_optional,
            );
        });

        return OpportunityData::fromModel($opportunity->fresh(['items']));
    }
}
