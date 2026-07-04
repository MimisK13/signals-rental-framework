<?php

namespace App\Services\Pricing;

use App\ValueObjects\Pricing\DiscountCriteriaContext;

/**
 * Pure criteria matcher for discount rule definitions.
 *
 * Evaluates whether a rule's JSONB criteria match a line/product context. Criteria
 * dimensions combine with AND logic; empty or absent criteria match everything.
 * Does not evaluate temporal validity — that belongs to P7 auto-apply.
 */
class CriteriaMatcher
{
    public function __construct(
        private readonly DiscountCriteriaPredicateRegistry $predicates,
    ) {}

    /**
     * @param  array<string, mixed>|null  $criteria
     */
    public function matches(?array $criteria, DiscountCriteriaContext $context): bool
    {
        $active = $this->activeCriteria($criteria);

        if ($active === []) {
            return true;
        }

        foreach ($this->predicates->all() as $key => $predicate) {
            if (! array_key_exists($key, $active)) {
                continue;
            }

            if (! $predicate->matches($active[$key], $context)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>|null  $criteria
     * @return array<string, mixed>
     */
    private function activeCriteria(?array $criteria): array
    {
        if ($criteria === null || $criteria === []) {
            return [];
        }

        $active = [];

        foreach ($criteria as $key => $value) {
            if ($value === null || $value === [] || $value === '') {
                continue;
            }

            $active[$key] = $value;
        }

        return $active;
    }
}
