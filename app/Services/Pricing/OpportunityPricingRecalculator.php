<?php

namespace App\Services\Pricing;

use App\Enums\DealPriceMode;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Services\Opportunities\OpportunityTotalsCalculator;
use App\Verbs\Events\Opportunities\DiscountReset;
use App\Verbs\Events\Opportunities\ItemDealPriceDiscountSet;
use App\Verbs\Events\Opportunities\ItemDiscountSet;
use App\Verbs\Events\Opportunities\ItemPriceOverridden;
use App\Verbs\Events\Opportunities\PricingRecalculated;

/**
 * Orchestrates opportunity repricing with optional reset toggles and active deal
 * price redistribution.
 */
class OpportunityPricingRecalculator
{
    public function __construct(
        private readonly DiscountCategoryApplier $discountCategoryApplier,
        private readonly DealPriceProportionalDistributor $dealPriceDistributor,
        private readonly OpportunityTotalsCalculator $calculator,
        private readonly OpportunityPricingCalculateDecorator $pricingDecorator,
    ) {}

    public function recalculate(
        Opportunity $opportunity,
        bool $resetPrices = false,
        bool $resetDiscounts = false,
    ): void {
        $chargeBefore = (int) $opportunity->charge_total;

        if ($resetPrices) {
            $this->resetManualPrices($opportunity);
        }

        if ($resetDiscounts) {
            $this->resetDiscounts($opportunity);
        }

        $this->reapplyActiveDealPrice($opportunity);

        $this->pricingDecorator->run(
            $opportunity->fresh(['items.item', 'member', 'costs']),
            fn () => $this->calculator->repriceOpportunity($opportunity->fresh(['items.item', 'member', 'costs'])),
        );

        $opportunity->refresh();

        PricingRecalculated::fire(
            opportunity_id: $opportunity->state_id,
            reset_prices: $resetPrices,
            reset_discounts: $resetDiscounts,
            charge_total_before: $chargeBefore,
            charge_total_after: (int) $opportunity->charge_total,
        );
    }

    private function resetManualPrices(Opportunity $opportunity): void
    {
        $opportunity->loadMissing('items');

        foreach ($opportunity->items as $item) {
            ItemPriceOverridden::fire(
                opportunity_item_id: (int) $item->state_id,
                unit_price: null,
            );
        }
    }

    private function resetDiscounts(Opportunity $opportunity): void
    {
        $opportunity->loadMissing('items');

        /** @var list<array{opportunity_item_id: int, discount_percent: ?string}> $applications */
        $applications = [];

        foreach ($opportunity->items as $item) {
            if (! $item->is_discount_manual) {
                continue;
            }

            $applications[] = [
                'opportunity_item_id' => (int) $item->id,
                'discount_percent' => $item->discount_percent !== null ? (string) $item->discount_percent : null,
            ];

            ItemDiscountSet::fire(
                opportunity_item_id: (int) $item->state_id,
                discount_percent: null,
                is_discount_manual: false,
            );
        }

        if ($applications !== []) {
            DiscountReset::fire(
                opportunity_id: $opportunity->state_id,
                applications: $applications,
            );
        }

        $this->discountCategoryApplier->apply($opportunity, resetManual: true);
    }

    private function reapplyActiveDealPrice(Opportunity $opportunity): void
    {
        $opportunity->loadMissing('items');

        if ($opportunity->deal_total !== null) {
            $mode = DealPriceMode::tryFrom((string) $opportunity->deal_price_mode)
                ?? DealPriceMode::FlatHeadline;

            if ($mode === DealPriceMode::Proportional) {
                $this->applyOpportunityDealDistribution($opportunity, (int) $opportunity->deal_total);

                return;
            }

            return;
        }

        $groupsWithDeal = $opportunity->items->filter(
            fn (OpportunityItem $item): bool => $item->deal_price !== null,
        );

        foreach ($groupsWithDeal as $group) {
            $this->applyGroupDealDistribution($opportunity, $group, (int) $group->deal_price);
        }
    }

    private function applyOpportunityDealDistribution(Opportunity $opportunity, int $dealTotal): void
    {
        $distribution = $this->dealPriceDistributor->distribute($opportunity, $dealTotal);

        foreach ($distribution['applications'] as $application) {
            ItemDealPriceDiscountSet::fire(
                opportunity_item_id: $application['state_id'],
                deal_price_discount_percent: $application['deal_price_discount_percent'],
            );
        }
    }

    private function applyGroupDealDistribution(Opportunity $opportunity, OpportunityItem $group, int $dealPrice): void
    {
        $distribution = $this->dealPriceDistributor->distribute($opportunity, $dealPrice, $group);

        foreach ($distribution['applications'] as $application) {
            ItemDealPriceDiscountSet::fire(
                opportunity_item_id: $application['state_id'],
                deal_price_discount_percent: $application['deal_price_discount_percent'],
            );
        }
    }
}
