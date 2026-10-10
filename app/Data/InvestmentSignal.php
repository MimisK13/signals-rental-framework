<?php

namespace App\Data;

use App\Enums\InvestmentIntelligenceCompleteness;
use App\Enums\InvestmentSignalType;

final readonly class InvestmentSignal
{
    /**
     * @param  list<InvestmentEvidence>  $evidence
     * @param  list<string>  $limitations
     */
    public function __construct(
        public InvestmentSignalType $type,
        public string $explanation,
        public array $evidence = [],
        public InvestmentIntelligenceCompleteness $completeness = InvestmentIntelligenceCompleteness::Complete,
        public array $limitations = [],
    ) {}
}
