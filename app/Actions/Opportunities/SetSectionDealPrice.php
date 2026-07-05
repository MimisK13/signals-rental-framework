<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\OpportunityData;
use App\Data\Opportunities\SetSectionDealPriceData;
use App\Enums\OpportunityItemType;
use App\Guards\Opportunities\GuardPipeline;
use App\Guards\Opportunities\TransitionContext;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Services\Pricing\DealPriceProportionalDistributor;
use App\Verbs\Events\Opportunities\GroupDealPriceSet;
use App\Verbs\Events\Opportunities\ItemDealPriceDiscountSet;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SetSectionDealPrice
{
    use CommitsVerbsEvents;

    public function __invoke(
        Opportunity $opportunity,
        OpportunityItem $group,
        SetSectionDealPriceData $data,
    ): OpportunityData {
        Gate::authorize('opportunities.edit');

        if ($group->opportunity_id !== $opportunity->id) {
            throw ValidationException::withMessages([
                'group' => 'The section does not belong to this opportunity.',
            ]);
        }

        if ($group->item_type !== OpportunityItemType::Group) {
            throw ValidationException::withMessages([
                'group' => 'Deal price can only be set on a group line item.',
            ]);
        }

        if ($opportunity->hasLocks()) {
            throw ValidationException::withMessages([
                'opportunity' => 'Unlock price before setting a deal price.',
            ]);
        }

        $this->commitVerbs(function () use ($opportunity, $group, $data): void {
            app(GuardPipeline::class)->run(new TransitionContext(
                transition: 'opportunity.set_section_deal_price',
                opportunity: $opportunity,
                permission: 'opportunities.edit',
            ));

            $distribution = app(DealPriceProportionalDistributor::class)->distribute(
                $opportunity->fresh(['items.item', 'member']),
                $data->deal_price,
                $group,
            );

            $lineApplications = [];

            foreach ($distribution['applications'] as $application) {
                ItemDealPriceDiscountSet::fire(
                    opportunity_item_id: $application['state_id'],
                    deal_price_discount_percent: $application['deal_price_discount_percent'],
                );

                $lineApplications[] = [
                    'opportunity_item_id' => $application['opportunity_item_id'],
                    'deal_price_discount_percent' => $application['deal_price_discount_percent'],
                ];
            }

            GroupDealPriceSet::fire(
                group_opportunity_item_id: (int) $group->state_id,
                deal_price: $data->deal_price,
                deal_price_original_total: $distribution['original_total'],
                line_applications: $lineApplications,
            );
        });

        return OpportunityData::fromModel($opportunity->fresh(['items']));
    }
}
