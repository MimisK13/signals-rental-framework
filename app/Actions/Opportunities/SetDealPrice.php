<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\OpportunityData;
use App\Data\Opportunities\SetDealPriceData;
use App\Enums\DealPriceMode;
use App\Guards\Opportunities\GuardPipeline;
use App\Guards\Opportunities\TransitionContext;
use App\Models\Opportunity;
use App\Services\Pricing\DealPriceProportionalDistributor;
use App\Verbs\Events\Opportunities\DealPriceSet;
use App\Verbs\Events\Opportunities\ItemDealPriceDiscountSet;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SetDealPrice
{
    use CommitsVerbsEvents;

    public function __invoke(Opportunity $opportunity, SetDealPriceData $data): OpportunityData
    {
        Gate::authorize('opportunities.edit');

        if ($opportunity->hasLocks()) {
            throw ValidationException::withMessages([
                'opportunity' => 'Unlock price before setting a deal price.',
            ]);
        }

        $this->commitVerbs(function () use ($opportunity, $data): void {
            app(GuardPipeline::class)->run(new TransitionContext(
                transition: 'opportunity.set_deal_price',
                opportunity: $opportunity,
                permission: 'opportunities.edit',
            ));

            $lineApplications = [];
            $originalTotal = null;

            if ($data->deal_price_mode === DealPriceMode::Proportional) {
                $distribution = app(DealPriceProportionalDistributor::class)->distribute(
                    $opportunity->fresh(['items.item', 'member']),
                    $data->deal_total,
                );

                $originalTotal = $distribution['original_total'];

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
            } else {
                $itemsWithDealDiscount = $opportunity->fresh('items')->items
                    ->filter(fn ($item) => $item->deal_price_discount_percent !== null);

                foreach ($itemsWithDealDiscount as $item) {
                    ItemDealPriceDiscountSet::fire(
                        opportunity_item_id: (int) $item->state_id,
                        deal_price_discount_percent: null,
                    );
                }
            }

            DealPriceSet::fire(
                opportunity_id: $opportunity->state_id,
                deal_total: $data->deal_total,
                deal_price_mode: $data->deal_price_mode->value,
                deal_price_original_total: $originalTotal,
                line_applications: $lineApplications,
            );
        });

        return OpportunityData::fromModel($opportunity->fresh(['items']));
    }
}
