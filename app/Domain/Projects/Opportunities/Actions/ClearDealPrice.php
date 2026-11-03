<?php

declare(strict_types=1);

namespace App\Domain\Projects\Opportunities\Actions;

use App\Concerns\CommitsVerbsEvents;
use App\Domain\Projects\Opportunities\Data\OpportunityData;
use App\Domain\Projects\Opportunities\Models\Opportunity;
use App\Domain\Projects\Opportunities\Models\OpportunityItem;
use App\Domain\Projects\Opportunities\Guards\GuardPipeline;
use App\Domain\Projects\Opportunities\Guards\TransitionContext;
use App\Domain\Projects\Opportunities\Verbs\Events\DealPriceCleared;
use App\Domain\Projects\Opportunities\Verbs\Events\ItemDealPriceDiscountSet;

class ClearDealPrice
{
    use CommitsVerbsEvents;

    public function __invoke(Opportunity $opportunity): OpportunityData
    {
        $this->commitVerbs(function () use ($opportunity): void {
            app(GuardPipeline::class)->run(new TransitionContext(
                transition: 'opportunity.clear_deal_price',
                opportunity: $opportunity,
                permission: 'opportunities.edit',
            ));

            $itemsWithDealDiscount = OpportunityItem::query()
                ->where('opportunity_id', $opportunity->id)
                ->whereNotNull('deal_price_discount_percent')
                ->get();

            foreach ($itemsWithDealDiscount as $item) {
                ItemDealPriceDiscountSet::fire(
                    opportunity_item_id: (int) $item->state_id,
                    deal_price_discount_percent: null,
                );
            }

            DealPriceCleared::fire(opportunity_id: $opportunity->state_id);
        });

        return OpportunityData::fromModel($opportunity->fresh(['items']));
    }
}
