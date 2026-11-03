<?php

declare(strict_types=1);

namespace App\Domain\Products\Investment\Data;

use App\Domain\Products\Investment\Enums\InvestmentIntelligenceCompleteness;
use App\Domain\Products\Investment\Enums\InvestmentSignalType;

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
