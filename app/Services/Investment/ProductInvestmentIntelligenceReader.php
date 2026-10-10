<?php

namespace App\Services\Investment;

use App\Data\InvestmentComponentCompleteness;
use App\Enums\InvestmentIntelligenceCompleteness;
use App\Enums\StockMethod;
use App\Enums\UtilizationCompleteness;
use App\Models\Product;
use App\Services\Assets\ProductFinancialsCalculator;
use App\Services\Assets\ProductUtilizationCalculator;
use App\Services\Products\ProductExternalRentalCostCalculator;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;

final class ProductInvestmentIntelligenceReader
{
    public function __construct(
        private readonly ProductFinancialsCalculator $financials,
        private readonly ProductUtilizationCalculator $utilization,
        private readonly ProductExternalRentalCostCalculator $externalRental,
        private readonly ProductWindowedRevenueReader $revenue,
        private readonly ProductShortageSummaryReader $shortages,
        private readonly InvestmentSignalEvaluator $evaluator,
    ) {}

    public function read(Product $product, CarbonImmutable $from, CarbonImmutable $until, CarbonImmutable $asOf): ProductInvestmentIntelligenceResult
    {
        if ($from->greaterThanOrEqualTo($until)) {
            throw new \InvalidArgumentException('The investment window must end after it starts.');
        }

        abort_unless($product->stock_method === StockMethod::Serialised, 404);

        $effectiveUntil = $until->lessThanOrEqualTo($asOf) ? $until : $asOf;
        $financials = $this->financials->calculate($product);
        $utilization = $this->utilization->calculate($product, $from, $until, $asOf);
        $externalRental = $this->externalRental->calculate($product, $from, $effectiveUntil);
        $windowedRevenue = $this->revenue->read($product, $from, $effectiveUntil, $asOf);
        $shortage = $this->shortages->read($product, $from, $until, $asOf);

        $subhireRatio = $this->subhireRatio($externalRental->actualCost, $externalRental->currencyCode, $windowedRevenue->amount, $windowedRevenue->currencyCode, $externalRental->actualCostComplete && $windowedRevenue->complete);
        $externalCompleteness = $externalRental->actualCostComplete && $windowedRevenue->complete && ($externalRental->actualCost === null || $subhireRatio !== null)
            ? InvestmentIntelligenceCompleteness::Complete
            : InvestmentIntelligenceCompleteness::Partial;
        $signals = $this->evaluator->productSignals(
            $utilization,
            $subhireRatio,
            $externalCompleteness,
            $shortage->occurrenceCount,
            $shortage->coverage,
            $from,
            $until,
        );

        $components = [
            'financial' => $financials->financialsComplete ? InvestmentIntelligenceCompleteness::Complete : InvestmentIntelligenceCompleteness::Partial,
            'utilization' => $this->utilizationCompleteness($utilization->completeness),
            'lifecycle' => $utilization->assetsWithIncompleteData > 0 ? InvestmentIntelligenceCompleteness::Partial : InvestmentIntelligenceCompleteness::Complete,
            'externalRental' => $externalCompleteness,
            'shortage' => $shortage->coverage,
        ];

        $reasons = array_values(array_unique(array_merge($financials->unavailableReasons, $utilization->unavailableReasons, $externalRental->unavailableReasons, $windowedRevenue->reasons, $shortage->limitations)));
        $completeness = $this->completeness($components, $reasons);

        return new ProductInvestmentIntelligenceResult(
            product: $product,
            windowStart: $from->utc(),
            windowEnd: $until->utc(),
            asOf: $asOf->utc(),
            financials: $financials,
            utilization: $utilization,
            externalRental: $externalRental,
            shortageSummary: $shortage,
            windowedRentalRevenue: $windowedRevenue->amount,
            windowedRentalRevenueCurrency: $windowedRevenue->currencyCode,
            subhireCostRatio: $subhireRatio,
            referenceAcquisitionCostEquivalent: $this->referenceAcquisitionCostEquivalent($product, $externalRental->actualCost, $externalRental->currencyCode),
            signals: $signals,
            guidance: $this->evaluator->guidance($signals, false),
            completeness: $completeness,
        );
    }

    private function subhireRatio(?int $cost, ?string $costCurrency, int $revenue, ?string $revenueCurrency, bool $complete): ?string
    {
        if (! $complete || $cost === null || $revenue <= 0 || $costCurrency === null || $revenueCurrency === null || strtoupper($costCurrency) !== strtoupper($revenueCurrency)) {
            return null;
        }

        return BigDecimal::of((string) $cost)
            ->dividedBy((string) $revenue, 10, RoundingMode::HALF_UP)
            ->toScale(10, RoundingMode::HALF_UP)
            ->__toString();
    }

    private function referenceAcquisitionCostEquivalent(Product $product, ?int $actualCost, ?string $actualCurrency): ?float
    {
        if ($actualCost === null || $actualCost <= 0 || $actualCurrency === null) {
            return null;
        }

        $reference = (int) ($product->purchase_price ?? 0);
        $referenceCurrency = (string) settings('company.base_currency', 'GBP');

        if ($reference <= 0) {
            $preferred = $product->supplierCosts()->preferred()->first();
            $reference = (int) ($preferred->cost ?? 0);
            $referenceCurrency = (string) ($preferred->currency_code ?? '');
        }

        if ($reference <= 0 || strtoupper($referenceCurrency) !== strtoupper($actualCurrency)) {
            return null;
        }

        return round($actualCost / $reference, 4);
    }

    private function utilizationCompleteness(UtilizationCompleteness $completeness): InvestmentIntelligenceCompleteness
    {
        return match ($completeness) {
            UtilizationCompleteness::Complete => InvestmentIntelligenceCompleteness::Complete,
            UtilizationCompleteness::Partial => InvestmentIntelligenceCompleteness::Partial,
            UtilizationCompleteness::Unavailable => InvestmentIntelligenceCompleteness::Insufficient,
        };
    }

    /**
     * @param  array<string, InvestmentIntelligenceCompleteness>  $components
     * @param  list<string>  $reasons
     */
    private function completeness(array $components, array $reasons): InvestmentComponentCompleteness
    {
        $values = array_map(fn (InvestmentIntelligenceCompleteness $value): string => $value->value, $components);
        $overall = in_array(InvestmentIntelligenceCompleteness::Insufficient->value, $values, true)
            ? InvestmentIntelligenceCompleteness::Partial
            : (in_array(InvestmentIntelligenceCompleteness::Partial->value, $values, true) ? InvestmentIntelligenceCompleteness::Partial : InvestmentIntelligenceCompleteness::Complete);

        return new InvestmentComponentCompleteness($overall, $components, $reasons);
    }
}
