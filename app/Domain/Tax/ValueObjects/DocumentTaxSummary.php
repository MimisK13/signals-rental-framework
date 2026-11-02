<?php

declare(strict_types=1);

namespace App\Domain\Tax\ValueObjects;

/**
 * Full tax breakdown for a document basket: grouped totals plus per-line allocations.
 */
final class DocumentTaxSummary
{
    /**
     * @param  list<DocumentTaxGroup>  $groups
     * @param  list<DocumentTaxLineAllocation>  $lineAllocations
     */
    public function __construct(
        public readonly array $groups,
        public readonly array $lineAllocations,
        public readonly int $totalNet,
        public readonly int $totalTax,
    ) {}

    public function totalGross(): int
    {
        return $this->totalNet + $this->totalTax;
    }

    /**
     * @return array<string, DocumentTaxLineAllocation>
     */
    public function lineAllocationsByKey(): array
    {
        $indexed = [];

        foreach ($this->lineAllocations as $allocation) {
            $indexed[$allocation->lineKey] = $allocation;
        }

        return $indexed;
    }
}
