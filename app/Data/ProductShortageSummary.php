<?php

namespace App\Data;

use App\Enums\InvestmentIntelligenceCompleteness;
use Carbon\CarbonImmutable;

final readonly class ProductShortageSummary
{
    /**
     * @param  list<string>  $limitations
     */
    public function __construct(
        public CarbonImmutable $windowStart,
        public CarbonImmutable $windowEnd,
        public InvestmentIntelligenceCompleteness $coverage,
        public ?int $occurrenceCount,
        public ?int $peakShortageQuantity,
        public array $limitations = [],
    ) {}
}
