<?php

namespace App\Services\Pricing;

use App\Models\OpportunityItem;

class OpportunityItemDiscountPercentFilterRegistry
{
    /** @var array<string, callable(OpportunityItem, string|null): (?string)> */
    private array $filters = [];

    /**
     * @param  callable(OpportunityItem, string|null): (?string)  $filter
     */
    public function register(string $key, callable $filter): void
    {
        $this->filters[$key] = $filter;
    }

    public function apply(OpportunityItem $item, ?string $percent): ?string
    {
        $current = $percent;

        foreach ($this->filters as $filter) {
            $current = $filter($item, $current);
        }

        return $current;
    }

    /**
     * @return array<string, callable(OpportunityItem, string|null): (?string)>
     */
    public function all(): array
    {
        return $this->filters;
    }
}
