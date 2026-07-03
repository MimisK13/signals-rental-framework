<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\ApplyDiscountCategoryData;
use App\Data\Opportunities\OpportunityData;
use App\Guards\Opportunities\GuardPipeline;
use App\Guards\Opportunities\TransitionContext;
use App\Models\Opportunity;
use App\Services\Pricing\DiscountCategoryApplier;
use Illuminate\Support\Facades\Gate;

/**
 * Applies the opportunity member's discount category rules to eligible line items
 * via {@see DiscountCategoryApplied} + {@see ItemDiscountSet} events.
 */
class ApplyDiscountCategory
{
    use CommitsVerbsEvents;

    public function __invoke(Opportunity $opportunity, ApplyDiscountCategoryData $data): OpportunityData
    {
        Gate::authorize('opportunities.edit');

        $this->commitVerbs(function () use ($opportunity, $data): void {
            app(GuardPipeline::class)->run(new TransitionContext(
                transition: 'opportunity.apply_discount_category',
                opportunity: $opportunity,
                permission: 'opportunities.edit',
            ));

            app(DiscountCategoryApplier::class)->apply(
                opportunity: $opportunity,
                opportunityItemIds: $data->opportunity_item_ids ?? [],
                resetManual: $data->reset_manual,
            );
        });

        return OpportunityData::fromModel($opportunity->fresh(['items']));
    }
}
