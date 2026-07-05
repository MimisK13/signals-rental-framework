<?php

namespace App\ValueObjects\Tax;

/**
 * Per-line tax allocation after largest-remainder reconciliation.
 */
class DocumentTaxLineAllocation
{
    public function __construct(
        public readonly string $lineKey,
        public readonly int $netMinor,
        public readonly int $taxMinor,
        public readonly int $productTaxClassKey,
    ) {}

    public function grossMinor(): int
    {
        return $this->netMinor + $this->taxMinor;
    }
}
