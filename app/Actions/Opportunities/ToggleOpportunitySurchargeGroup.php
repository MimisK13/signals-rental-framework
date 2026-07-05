<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\OpportunityData;
use App\Data\Opportunities\ToggleOpportunitySurchargeGroupData;
use App\Guards\Opportunities\GuardPipeline;
use App\Guards\Opportunities\TransitionContext;
use App\Models\Opportunity;
use App\Verbs\Events\Opportunities\SurchargeToggled;
use Illuminate\Support\Facades\Gate;

class ToggleOpportunitySurchargeGroup
{
    use CommitsVerbsEvents;

    public function __invoke(Opportunity $opportunity, ToggleOpportunitySurchargeGroupData $data): OpportunityData
    {
        Gate::authorize('opportunities.edit');

        $this->commitVerbs(function () use ($opportunity, $data): void {
            app(GuardPipeline::class)->run(new TransitionContext(
                transition: 'opportunity.toggle_surcharge_group',
                opportunity: $opportunity,
                permission: 'opportunities.edit',
            ));

            SurchargeToggled::fire(
                opportunity_id: $opportunity->state_id,
                surcharge_group_id: $data->surcharge_group_id,
                is_active: $data->is_active,
            );
        });

        return OpportunityData::fromModel($opportunity->fresh(['items.itemSurcharges']));
    }
}
