<?php

declare(strict_types=1);

namespace App\Domain\Products\Investment\Data;

use App\Domain\Products\Investment\Enums\InvestmentIntelligenceCompleteness;

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
