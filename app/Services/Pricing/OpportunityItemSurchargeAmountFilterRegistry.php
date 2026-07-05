<?php

namespace App\Services\Pricing;

use App\Models\OpportunityItem;

/**
 * Plugin filter hook for `opportunity_item.surcharge_amount`.
 *
 * Filters receive the computed surcharge amount as a decimal string in minor
 * units before per-line rounding is applied (mirrors
 * {@see OpportunityItemDiscountPercentFilterRegistry}, which filters the
 * discount percent before the derived discount amount is rounded).
 */
class OpportunityItemSurchargeAmountFilterRegistry
{
    /** @var array<string, callable(OpportunityItem, string, int): (?string)> */
    private array $filters = [];

    /**
     * @param  callable(OpportunityItem, string, int): (?string)  $filter
     */
    public function register(string $key, callable $filter): void
    {
        $this->filters[$key] = $filter;
    }

    /**
     * @param  string  $rawAmountMinor  Unrounded computed amount as a decimal string
     */
    public function apply(OpportunityItem $item, string $rawAmountMinor, int $surchargeId): ?string
    {
        $current = $rawAmountMinor;

        foreach ($this->filters as $filter) {
            $current = $filter($item, $current, $surchargeId);

            if ($current === null) {
                return null;
            }
        }

        return $current;
    }

    /**
     * @return array<string, callable(OpportunityItem, string, int): (?string)>
     */
    public function all(): array
    {
        return $this->filters;
    }
}
