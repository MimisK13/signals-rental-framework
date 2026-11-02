<?php

declare(strict_types=1);

namespace App\Domain\Quotations\ValueObjects;

final readonly class QuotationLinePricing
{
    /** @param array<string, mixed> $breakdown */
    public function __construct(
        public int $unitPrice,
        public int $netTotal,
        public int $taxTotal,
        public int $grossTotal,
        public ?int $rateDefinitionId,
        public ?string $taxRate,
        public array $breakdown,
    ) {}
}
