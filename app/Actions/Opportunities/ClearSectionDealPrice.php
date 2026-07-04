<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\OpportunityData;
use App\Enums\OpportunityItemType;
use App\Guards\Opportunities\GuardPipeline;
use App\Guards\Opportunities\TransitionContext;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Services\Pricing\DealPriceProportionalDistributor;
use App\Verbs\Events\Opportunities\GroupDealPriceCleared;
use App\Verbs\Events\Opportunities\ItemDealPriceDiscountSet;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ClearSectionDealPrice
{
    use CommitsVerbsEvents;

    public function __invoke(Opportunity $opportunity, OpportunityItem $group): OpportunityData
    {
        Gate::authorize('opportunities.edit');

        if ($group->opportunity_id !== $opportunity->id) {
            throw ValidationException::withMessages([
                'group' => 'The section does not belong to this opportunity.',
            ]);
        }

        if ($group->item_type !== OpportunityItemType::Group) {
            throw ValidationException::withMessages([
                'group' => 'Deal price can only be cleared on a group line item.',
            ]);
        }

        if ($group->deal_price === null) {
            throw ValidationException::withMessages([
                'group' => 'This section does not have an active deal price.',
            ]);
        }

        if ($opportunity->hasLocks()) {
            throw ValidationException::withMessages([
                'opportunity' => 'Unlock price before clearing a deal price.',
            ]);
        }

        $this->commitVerbs(function () use ($opportunity, $group): void {
            app(GuardPipeline::class)->run(new TransitionContext(
                transition: 'opportunity.clear_section_deal_price',
                opportunity: $opportunity,
                permission: 'opportunities.edit',
            ));

            $lineApplications = [];
            $scopedItems = app(DealPriceProportionalDistributor::class)
                ->scopedItemsForGroup($opportunity->fresh(['items.item', 'member']), $group);

            foreach ($scopedItems as $item) {
                if ($item->deal_price_discount_percent === null) {
                    continue;
                }

                ItemDealPriceDiscountSet::fire(
                    opportunity_item_id: (int) $item->state_id,
                    deal_price_discount_percent: null,
                );

                $lineApplications[] = [
                    'opportunity_item_id' => (int) $item->id,
                    'deal_price_discount_percent' => null,
                ];
            }

            GroupDealPriceCleared::fire(
                group_opportunity_item_id: (int) $group->state_id,
                line_applications: $lineApplications,
            );
        });

        return OpportunityData::fromModel($opportunity->fresh(['items']));
    }
}
