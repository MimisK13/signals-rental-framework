<?php

declare(strict_types=1);

namespace App\Domain\Tax\ValueObjects;

/**
 * Per-line tax allocation after largest-remainder reconciliation.
 */
final class DocumentTaxLineAllocation
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
