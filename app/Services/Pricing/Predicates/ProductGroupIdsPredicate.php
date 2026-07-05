<?php

namespace App\Services\Pricing\Predicates;

use App\Contracts\Pricing\DiscountCriteriaPredicate;
use App\ValueObjects\Pricing\DiscountCriteriaContext;

class ProductGroupIdsPredicate implements DiscountCriteriaPredicate
{
    public function key(): string
    {
        return 'product_group_ids';
    }

    public function matches(mixed $criterionValue, DiscountCriteriaContext $context): bool
    {
        if (! is_array($criterionValue) || $criterionValue === []) {
            return true;
        }

        if ($context->product_group_id === null) {
            return false;
        }

        $ids = array_map(intval(...), $criterionValue);

        return in_array($context->product_group_id, $ids, true);
    }
}
