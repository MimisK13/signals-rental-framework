<?php

namespace App\Services\Pricing;

use App\Enums\DiscountRuleScope;
use App\Enums\LineItemTransactionType;
use App\Enums\OpportunityItemType;
use App\Enums\RateTransactionType;
use App\Models\DiscountRule;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\ValueObjects\Pricing\DiscountCriteriaContext;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Evaluates an organisation's discount-category rules for a line-item context.
 *
 * Uses {@see CriteriaMatcher} for predicate matching, applies highest-wins across
 * all matching rules, skips non-discountable catalogue lines, and enforces each
 * rule's `valid_from` / `valid_to` window against the supplied evaluation instant.
 */
class DiscountRuleEvaluator
{
    public function __construct(
        private readonly CriteriaMatcher $matcher,
    ) {}

    /**
     * @param  Collection<int, DiscountRule>  $rules
     * @param  array{by_product: array<int, int>, by_group: array<int, int>}  $quantityMaps
     */
    public function evaluateForItem(
        OpportunityItem $item,
        Opportunity $opportunity,
        Collection $rules,
        CarbonInterface $at,
        array $quantityMaps = [],
    ): ?string {
        if (! $this->isDiscountable($item)) {
            return null;
        }

        $matching = [];

        foreach ($rules as $rule) {
            if (! $this->isTemporallyValid($rule, $at)) {
                continue;
            }

            $scope = $rule->rule_scope ?? DiscountRuleScope::PerLine;
            $context = $this->contextFromItem($item, $quantityMaps)->withRuleScope($scope);

            if (! $this->matcher->matches($rule->criteria, $context)) {
                continue;
            }

            $matching[] = (string) $rule->discount_percent;
        }

        if ($matching === []) {
            return null;
        }

        return $this->highestPercent($matching);
    }

    public function evaluationInstant(OpportunityItem $item, Opportunity $opportunity): CarbonInterface
    {
        return $item->starts_at ?? $opportunity->starts_at ?? now('UTC');
    }

    private function isDiscountable(OpportunityItem $item): bool
    {
        if (! $item->item_type->isPriceable()) {
            return false;
        }

        if ($item->item_type === OpportunityItemType::Text) {
            return true;
        }

        if (! $item->isProductBacked()) {
            return true;
        }

        $product = $item->relationLoaded('item') ? $item->item : null;

        if (! $product instanceof Product) {
            $product = Product::query()->find($item->itemable_id);
        }

        return $product?->discountable ?? true;
    }

    private function isTemporallyValid(DiscountRule $rule, CarbonInterface $at): bool
    {
        if ($rule->valid_from !== null && $at->lt($rule->valid_from)) {
            return false;
        }

        if ($rule->valid_to !== null && $at->gt($rule->valid_to)) {
            return false;
        }

        return true;
    }

    /**
     * @param  array{by_product: array<int, int>, by_group: array<int, int>}  $quantityMaps
     */
    private function contextFromItem(OpportunityItem $item, array $quantityMaps): DiscountCriteriaContext
    {
        $productGroupId = null;
        $tagNames = [];
        $productId = null;
        $lineQuantity = max(0, (int) round((float) $item->quantity));
        $aggregateProduct = null;
        $aggregateGroup = null;

        if ($item->isProductBacked()) {
            $product = $item->relationLoaded('item') ? $item->item : null;

            if (! $product instanceof Product) {
                $product = Product::query()->find($item->itemable_id);
            }

            if ($product instanceof Product) {
                $productId = (int) $product->id;
                $productGroupId = $product->product_group_id !== null ? (int) $product->product_group_id : null;
                $tagNames = array_values(array_filter(
                    is_array($product->tag_list) ? $product->tag_list : [],
                    is_string(...),
                ));
                $aggregateProduct = $quantityMaps['by_product'][$productId] ?? null;
            }
        }

        $groupKey = (int) ($item->revenue_group_id ?? 0);
        $aggregateGroup = $quantityMaps['by_group'][$groupKey] ?? null;

        return new DiscountCriteriaContext(
            product_group_id: $productGroupId,
            tag_names: $tagNames,
            product_id: $productId,
            transaction_type: $this->mapTransactionType($item->transaction_type),
            line_quantity: $lineQuantity,
            aggregate_product_quantity: $aggregateProduct,
            aggregate_group_quantity: $aggregateGroup,
        );
    }

    private function mapTransactionType(LineItemTransactionType $type): ?string
    {
        return match ($type) {
            LineItemTransactionType::Rental, LineItemTransactionType::SubRental => RateTransactionType::Rental->value,
            LineItemTransactionType::Sale => RateTransactionType::Sale->value,
            LineItemTransactionType::Service => RateTransactionType::Service->value,
        };
    }

    /**
     * @param  list<string>  $percents
     */
    private function highestPercent(array $percents): string
    {
        $winner = '0.00';

        foreach ($percents as $percent) {
            if (bccomp($percent, $winner, 2) > 0) {
                $winner = $percent;
            }
        }

        return $winner;
    }
}
