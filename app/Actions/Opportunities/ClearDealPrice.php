<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\OpportunityData;
use App\Guards\Opportunities\GuardPipeline;
use App\Guards\Opportunities\TransitionContext;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Verbs\Events\Opportunities\DealPriceCleared;
use App\Verbs\Events\Opportunities\ItemDealPriceDiscountSet;

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
