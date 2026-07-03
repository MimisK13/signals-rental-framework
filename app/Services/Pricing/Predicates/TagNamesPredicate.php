<?php

namespace App\Services\Pricing\Predicates;

use App\Contracts\Pricing\DiscountCriteriaPredicate;
use App\ValueObjects\Pricing\DiscountCriteriaContext;

/**
 * Tag criteria use AND logic — every configured tag must be present on the line/product.
 */
class TagNamesPredicate implements DiscountCriteriaPredicate
{
    public function key(): string
    {
        return 'tag_names';
    }

    public function matches(mixed $criterionValue, DiscountCriteriaContext $context): bool
    {
        if (! is_array($criterionValue) || $criterionValue === []) {
            return true;
        }

        $required = array_values(array_filter(array_map(
            static fn (mixed $tag): ?string => is_string($tag) && $tag !== '' ? mb_strtolower($tag) : null,
            $criterionValue,
        )));

        if ($required === []) {
            return true;
        }

        $available = array_map(
            static fn (string $tag): string => mb_strtolower($tag),
            $context->tag_names,
        );

        foreach ($required as $tag) {
            if (! in_array($tag, $available, true)) {
                return false;
            }
        }

        return true;
    }
}
