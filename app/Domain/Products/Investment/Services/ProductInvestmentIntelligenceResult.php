<?php

declare(strict_types=1);

namespace App\Domain\Products\Investment\Services;

use App\Domain\Products\Investment\Data\InvestmentComponentCompleteness;
use App\Domain\Products\Investment\Data\InvestmentSignal;
use App\Domain\Products\Investment\Data\ProductShortageSummary;
use App\Domain\Products\Models\Product;
use App\Domain\Products\Investment\Enums\InvestmentGuidance;
use App\Domain\Assets\Services\ProductFinancialsResult;
use App\Domain\Assets\Services\ProductUtilizationResult;
use App\Domain\Products\Services\ProductExternalRentalCostResult;
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
