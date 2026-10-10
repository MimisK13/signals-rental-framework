<?php

namespace App\Data;

use App\Enums\InvestmentIntelligenceCompleteness;

final readonly class InvestmentComponentCompleteness
{
    /**
     * @param  array<string, InvestmentIntelligenceCompleteness>  $components
     * @param  list<string>  $reasons
     */
    public function __construct(
        public InvestmentIntelligenceCompleteness $overall,
        public array $components,
        public array $reasons = [],
    ) {}
}
