<?php

declare(strict_types=1);

namespace App\Domain\Products\Investment\Data;

use App\Domain\Products\Investment\Enums\InvestmentIntelligenceCompleteness;

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
