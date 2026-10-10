<?php

namespace App\Services\Investment;

use App\Data\InvestmentComponentCompleteness;
use App\Data\InvestmentSignal;
use App\Data\ProductShortageSummary;
use App\Enums\InvestmentGuidance;
use App\Models\Product;
use App\Services\Assets\ProductFinancialsResult;
use App\Services\Assets\ProductUtilizationResult;
use App\Services\Products\ProductExternalRentalCostResult;
use Carbon\CarbonImmutable;

final readonly class ProductInvestmentIntelligenceResult
{
    /**
     * @param  list<InvestmentSignal>  $signals
     * @param  list<InvestmentGuidance>  $guidance
     */
    public function __construct(
        public Product $product,
        public CarbonImmutable $windowStart,
        public CarbonImmutable $windowEnd,
        public CarbonImmutable $asOf,
        public ProductFinancialsResult $financials,
        public ProductUtilizationResult $utilization,
        public ProductExternalRentalCostResult $externalRental,
        public ProductShortageSummary $shortageSummary,
        public ?int $windowedRentalRevenue,
        public ?string $windowedRentalRevenueCurrency,
        public ?string $subhireCostRatio,
        public ?float $referenceAcquisitionCostEquivalent,
        public array $signals,
        public array $guidance,
        public InvestmentComponentCompleteness $completeness,
    ) {}
}
