<?php

declare(strict_types=1);

namespace App\Domain\Projects\Opportunities\Actions;

use App\Concerns\CommitsVerbsEvents;
use App\Domain\Pricing\Services\DiscountCategoryApplier;
use App\Domain\Projects\Opportunities\Data\ApplyDiscountCategoryData;
use App\Domain\Projects\Opportunities\Data\OpportunityData;
use App\Domain\Projects\Opportunities\Guards\GuardPipeline;
use App\Domain\Projects\Opportunities\Guards\TransitionContext;
use App\Domain\Projects\Opportunities\Models\Opportunity;
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
