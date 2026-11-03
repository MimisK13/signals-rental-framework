<?php

declare(strict_types=1);

namespace App\Domain\Projects\Opportunities\Actions;

use App\Concerns\CommitsVerbsEvents;
use App\Domain\Projects\Opportunities\Data\OpportunityData;
use App\Domain\Projects\Opportunities\Models\OpportunityCost;
use App\Domain\Projects\Opportunities\Verbs\Events\CostRemoved;
use Illuminate\Support\Facades\Gate;

/**
 * Removes a cost from its opportunity via the CostRemoved event.
 */
class RemoveOpportunityCost
{
    use CommitsVerbsEvents;

    public function __invoke(OpportunityCost $cost): OpportunityData
    {
        Gate::authorize('opportunities.edit');

        $opportunity = $cost->opportunity()->firstOrFail();

        $this->commitVerbs(function () use ($cost): void {
            CostRemoved::fire(opportunity_cost_id: $cost->state_id);
        });

        return OpportunityData::fromModel($opportunity->fresh(['costs']));
    }
}
