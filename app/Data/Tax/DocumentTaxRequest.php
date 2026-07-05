<?php

namespace App\Data\Tax;

use App\Services\Tax\DocumentTaxBreakdown;
use DateTimeInterface;
use Spatie\LaravelData\Data;

/**
 * Input payload for {@see DocumentTaxBreakdown}.
 */
class DocumentTaxRequest extends Data
{
    /**
     * @param  list<DocumentTaxLineInput>  $lines
     */
    public function __construct(
        public array $lines,
        public string $currencyCode,
        public ?int $organisationTaxClassId = null,
        public ?DateTimeInterface $effectiveAt = null,
        public bool $pricesIncludeTax = false,
    ) {}
}
