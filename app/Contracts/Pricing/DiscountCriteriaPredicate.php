<?php

namespace App\Contracts\Pricing;

use App\ValueObjects\Pricing\DiscountCriteriaContext;

interface DiscountCriteriaPredicate
{
    /**
     * The criteria JSON key this predicate evaluates (e.g. `product_group_ids`).
     */
    public function key(): string;

    /**
     * @param  mixed  $criterionValue  The value stored under this predicate's key in rule criteria.
     */
    public function matches(mixed $criterionValue, DiscountCriteriaContext $context): bool;
}
