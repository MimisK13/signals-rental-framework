<?php

namespace App\Services\Pricing\Predicates;

use App\Contracts\Pricing\DiscountCriteriaPredicate;
use App\Enums\RateTransactionType;
use App\ValueObjects\Pricing\DiscountCriteriaContext;

class TransactionTypesPredicate implements DiscountCriteriaPredicate
{
    public function key(): string
    {
        return 'transaction_types';
    }

    public function matches(mixed $criterionValue, DiscountCriteriaContext $context): bool
    {
        if (! is_array($criterionValue) || $criterionValue === []) {
            return true;
        }

        if ($context->transaction_type === null) {
            return false;
        }

        $contextType = RateTransactionType::coerce($context->transaction_type);

        if (! is_string($contextType)) {
            return false;
        }

        $allowed = array_map(
            static fn (mixed $value): ?string => is_string($value) || is_int($value)
                ? (string) RateTransactionType::coerce($value)
                : null,
            $criterionValue,
        );

        $allowed = array_values(array_filter($allowed, is_string(...)));

        return in_array($contextType, $allowed, true);
    }
}
