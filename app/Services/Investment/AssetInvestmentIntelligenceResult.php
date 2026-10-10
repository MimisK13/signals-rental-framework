<?php

namespace App\Services\Investment;

use App\Data\InvestmentComponentCompleteness;
use App\Data\InvestmentSignal;
use App\Enums\InvestmentGuidance;
use App\Models\Asset;
use App\Services\Assets\AssetFinancialsResult;
use App\Services\Assets\AssetUtilizationResult;
use Carbon\CarbonImmutable;

final readonly class AssetInvestmentIntelligenceResult
{
    /**
     * @param  array<string, mixed>  $lifecycleSummary
     * @param  array<string, mixed>  $maintenanceSummary
     * @param  list<InvestmentSignal>  $signals
     * @param  list<InvestmentGuidance>  $guidance
     */
    public function __construct(
        public Asset $asset,
        public CarbonImmutable $windowStart,
        public CarbonImmutable $windowEnd,
        public CarbonImmutable $asOf,
        public AssetFinancialsResult $financials,
        public AssetUtilizationResult $utilization,
        public array $lifecycleSummary,
        public array $maintenanceSummary,
        public ?int $lifecycleInvestmentProfit,
        public ?string $lifecycleRoi,
        public array $signals,
        public array $guidance,
        public InvestmentComponentCompleteness $completeness,
    ) {}
}
