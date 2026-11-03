<?php

declare(strict_types=1);

namespace App\Domain\Projects\Opportunities\Actions;

use App\Concerns\CommitsVerbsEvents;
use App\Domain\Projects\Opportunities\Data\OpportunityData;
use App\Domain\Projects\Opportunities\Data\ToggleOpportunitySurchargeGroupData;
use App\Domain\Projects\Opportunities\Guards\GuardPipeline;
use App\Domain\Projects\Opportunities\Guards\TransitionContext;
use App\Domain\Projects\Opportunities\Models\Opportunity;
use App\Domain\Projects\Opportunities\Verbs\Events\SurchargeToggled;
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
