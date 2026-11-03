<?php

declare(strict_types=1);

namespace App\Domain\Projects\Opportunities\Actions;

use App\Concerns\CommitsVerbsEvents;
use App\Domain\Pricing\Services\DealPriceProportionalDistributor;
use App\Domain\Projects\Opportunities\Data\OpportunityData;
use App\Domain\Projects\Opportunities\Enums\OpportunityItemType;
use App\Domain\Projects\Opportunities\Guards\GuardPipeline;
use App\Domain\Projects\Opportunities\Guards\TransitionContext;
use App\Domain\Projects\Opportunities\Models\Opportunity;
use App\Domain\Projects\Opportunities\Models\OpportunityItem;
use App\Domain\Projects\Opportunities\Verbs\Events\GroupDealPriceCleared;
use App\Domain\Projects\Opportunities\Verbs\Events\ItemDealPriceDiscountSet;
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
