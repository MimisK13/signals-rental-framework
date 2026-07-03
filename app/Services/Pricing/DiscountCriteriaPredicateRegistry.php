<?php

namespace App\Services\Pricing;

use App\Contracts\Pricing\DiscountCriteriaPredicate;
use InvalidArgumentException;

/**
 * Plugin-extensible registry of discount rule criteria predicates.
 *
 * Core registers the four built-in criteria dimensions in {@see AppServiceProvider};
 * plugins register additional predicates through the same interface so the
 * {@see CriteriaMatcher} needs no changes when new dimensions arrive.
 */
class DiscountCriteriaPredicateRegistry
{
    /** @var array<string, DiscountCriteriaPredicate> */
    private array $predicates = [];

    public function register(DiscountCriteriaPredicate $predicate): void
    {
        $this->predicates[$predicate->key()] = $predicate;
    }

    /**
     * @throws InvalidArgumentException when the key is not registered
     */
    public function get(string $key): DiscountCriteriaPredicate
    {
        return $this->predicates[$key]
            ?? throw new InvalidArgumentException("Unknown discount criteria predicate: {$key}");
    }

    /**
     * @return array<string, DiscountCriteriaPredicate>
     */
    public function all(): array
    {
        return $this->predicates;
    }

    public function has(string $key): bool
    {
        return isset($this->predicates[$key]);
    }
}
