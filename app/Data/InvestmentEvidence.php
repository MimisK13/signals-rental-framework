<?php

namespace App\Data;

use App\Enums\InvestmentIntelligenceCompleteness;

final readonly class InvestmentEvidence
{
    /**
     * @param  list<string>  $limitations
     */
    public function __construct(
        public string $metric,
        public string $observed,
        public ?string $operator = null,
        public ?string $threshold = null,
        public ?string $window = null,
        public ?InvestmentIntelligenceCompleteness $completeness = null,
        public array $limitations = [],
    ) {}
}
