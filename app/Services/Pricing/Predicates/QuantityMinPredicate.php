<?php

namespace App\Services\Pricing\Predicates;

use App\Contracts\Pricing\DiscountCriteriaPredicate;
use App\Enums\DiscountRuleScope;
use App\ValueObjects\Pricing\DiscountCriteriaContext;

class QuantityMinPredicate implements DiscountCriteriaPredicate
{
    public function key(): string
    {
        return 'quantity_min';
    }

    public function matches(mixed $criterionValue, DiscountCriteriaContext $context): bool
    {
        if ($criterionValue === null || $criterionValue === '') {
            return true;
        }

        $minimum = (int) $criterionValue;
        $quantity = $this->resolvedQuantity($context);

        if ($quantity === null) {
            return false;
        }

        return $quantity >= $minimum;
    }

    private function resolvedQuantity(DiscountCriteriaContext $context): ?int
    {
        return match ($context->rule_scope ?? DiscountRuleScope::PerLine) {
            DiscountRuleScope::AggregateProduct => $context->aggregate_product_quantity,
            DiscountRuleScope::AggregateGroup => $context->aggregate_group_quantity,
            default => $context->line_quantity,
        };
    }
}
