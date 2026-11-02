<?php

declare(strict_types=1);

namespace App\Domain\Tax\Data;

use App\Domain\Tax\Services\DocumentTaxBreakdown;
use DateTimeInterface;
use Spatie\LaravelData\Data;

/**
 * Input payload for {@see DocumentTaxBreakdown}.
 */
final class DocumentTaxRequest extends Data
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
