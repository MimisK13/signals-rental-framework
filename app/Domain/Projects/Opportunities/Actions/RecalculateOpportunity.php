<?php

declare(strict_types=1);

namespace App\Domain\Projects\Opportunities\Actions;

use App\Concerns\CommitsVerbsEvents;
use App\Domain\Projects\Opportunities\Data\OpportunityData;
use App\Domain\Projects\Opportunities\Data\RecalculateOpportunityData;
use App\Domain\Projects\Opportunities\Models\Opportunity;
use App\Domain\Projects\Opportunities\Guards\GuardPipeline;
use App\Domain\Projects\Opportunities\Guards\TransitionContext;
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
