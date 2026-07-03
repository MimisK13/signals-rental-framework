<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\OpportunityData;
use App\Data\Opportunities\RecalculateOpportunityData;
use App\Guards\Opportunities\GuardPipeline;
use App\Guards\Opportunities\TransitionContext;
use App\Models\Opportunity;
use App\Services\Pricing\OpportunityPricingRecalculator;
use Illuminate\Support\Facades\Gate;

class RecalculateOpportunity
{
    use CommitsVerbsEvents;

    public function __invoke(Opportunity $opportunity, RecalculateOpportunityData $data): OpportunityData
    {
        Gate::authorize('opportunities.edit');

        $this->commitVerbs(function () use ($opportunity, $data): void {
            app(GuardPipeline::class)->run(new TransitionContext(
                transition: 'opportunity.recalculate_pricing',
                opportunity: $opportunity,
                permission: 'opportunities.edit',
            ));

            app(OpportunityPricingRecalculator::class)->recalculate(
                $opportunity,
                resetPrices: $data->reset_prices,
                resetDiscounts: $data->reset_discounts,
            );
        });

        return OpportunityData::fromModel($opportunity->fresh(['items']));
    }
}
