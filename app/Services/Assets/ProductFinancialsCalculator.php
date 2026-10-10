<?php

namespace App\Services\Assets;

use App\Enums\AssetOwnershipType;
use App\Enums\StockCategory;
use App\Models\Asset;
use App\Models\Product;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final class ProductFinancialsCalculator
{
    public function __construct(
        private readonly AssetFinancialsCalculator $assetCalculator,
    ) {}

    public function calculate(Product $product): ProductFinancialsResult
    {
        $product->loadMissing([
            'assets.currentStockLevel.product',
            'assets.expenses',
            'assets.usages.opportunityItem.opportunity',
        ]);

        $assets = $product->assets
            ->filter(fn (Asset $asset): bool => $this->isEligibleAsset($asset))
            ->values();

        $assetResults = $assets
            ->map(fn (Asset $asset): array => [
                'asset' => $asset,
                'financials' => $this->assetCalculator->calculate($asset),
            ])
            ->values()
            ->all();

        return $this->aggregate($assetResults);
    }

    /**
     * @param  list<array{asset: Asset, financials: AssetFinancialsResult}>  $assetResults
     */
    private function aggregate(array $assetResults): ProductFinancialsResult
    {
        $currencyCode = $this->reportingCurrency($assetResults);
        $reasons = [];
        $confirmedRevenue = 0;
        $currencyCompatible = true;
        $revenueComplete = true;
        $totalAcquisitionCost = 0;
        $acquisitionComplete = true;
        $operatingExpenses = 0;
        $expensesComplete = true;
        $netRentalIncome = null;
        $recoveredAssetCount = 0;
        $belowBreakEvenAssetCount = 0;
        $unusedAssetCount = 0;
        $incompleteAssetCount = 0;
        $assetParticipationCount = 0;
        $rentalJobIds = [];
        $completedRentalCount = 0;
        $actualRentalDays = 0;
        $usageComplete = true;

        foreach ($assetResults as $entry) {
            $asset = $entry['asset'];
            $financials = $entry['financials'];

            if ($financials->currencyCode !== $currencyCode) {
                $reasons[] = 'currency_unavailable';
                $currencyCompatible = false;
            }

            if (in_array('revenue_currency_unavailable', $financials->unavailableReasons, true)) {
                $currencyCompatible = false;
            }

            $confirmedRevenue += $financials->confirmedAttributedRentalRevenue;
            $revenueComplete = $revenueComplete && $financials->revenueComplete;
            $assetParticipationCount += $financials->rentalCount;
            $completedRentalCount += $financials->completedRentalCount;
            if ($financials->actualRentalDays === null) {
                $usageComplete = false;
            } else {
                $actualRentalDays += $financials->actualRentalDays;
            }

            foreach ($asset->usages as $usage) {
                $opportunityId = $usage->opportunityItem?->opportunity_id;

                if ($opportunityId !== null) {
                    $rentalJobIds[$opportunityId] = true;
                }
            }

            if ($asset->base_currency_code !== $currencyCode
                || $asset->base_acquisition_amount === null
                || $asset->base_acquisition_amount <= 0) {
                $acquisitionComplete = false;
            } else {
                $totalAcquisitionCost += $asset->base_acquisition_amount;
            }

            if ($financials->operatingExpenses === null) {
                $expensesComplete = false;
            } else {
                $operatingExpenses += $financials->operatingExpenses;
            }

            if ($financials->costRecoveryRatio !== null) {
                if (BigDecimal::of($financials->costRecoveryRatio)->isGreaterThanOrEqualTo('1')) {
                    $recoveredAssetCount++;
                } else {
                    $belowBreakEvenAssetCount++;
                }
            }

            if ($financials->rentalCount === 0) {
                $unusedAssetCount++;
            }

            if (! $financials->isFinanciallyComplete()) {
                $incompleteAssetCount++;
            }

            $reasons = [...$reasons, ...$financials->unavailableReasons];
        }

        $financialsComplete = $revenueComplete && $acquisitionComplete && $expensesComplete
            && ! in_array('currency_unavailable', $reasons, true);

        if (! $revenueComplete) {
            $reasons[] = 'revenue_incomplete';
        }

        if (! $acquisitionComplete) {
            $reasons[] = 'acquisition_incomplete';
        }

        if (! $expensesComplete) {
            $reasons[] = 'expense_incomplete';
        }

        if (! $usageComplete) {
            $reasons[] = 'usage_incomplete';
        }

        if ($financialsComplete) {
            $netRentalIncome = $confirmedRevenue - $operatingExpenses;
        }

        $costRecoveryRatio = null;
        $remainingToBreakEven = null;
        $investmentProfit = null;
        $investmentRoi = null;

        if ($netRentalIncome !== null && $totalAcquisitionCost > 0) {
            $costRecoveryRatio = $this->ratio($netRentalIncome, $totalAcquisitionCost);
            $remainingToBreakEven = max($totalAcquisitionCost - $netRentalIncome, 0);
            $investmentProfit = $netRentalIncome - $totalAcquisitionCost;
            $investmentRoi = $this->ratio($investmentProfit, $totalAcquisitionCost);
        }

        return new ProductFinancialsResult(
            assetCount: count($assetResults),
            totalAcquisitionCost: $acquisitionComplete ? $totalAcquisitionCost : null,
            confirmedAttributedRentalRevenue: $currencyCompatible ? $confirmedRevenue : null,
            operatingExpenses: $expensesComplete ? $operatingExpenses : null,
            netRentalIncome: $netRentalIncome,
            costRecoveryRatio: $costRecoveryRatio,
            remainingToBreakEven: $remainingToBreakEven,
            investmentProfit: $investmentProfit,
            investmentRoi: $investmentRoi,
            assetParticipationCount: $assetParticipationCount,
            rentalJobCount: count($rentalJobIds),
            completedRentalCount: $completedRentalCount,
            actualRentalDays: $usageComplete ? $actualRentalDays : null,
            currencyCode: $currencyCode,
            revenueComplete: $revenueComplete,
            financialsComplete: $financialsComplete,
            recoveredAssetCount: $recoveredAssetCount,
            belowBreakEvenAssetCount: $belowBreakEvenAssetCount,
            unusedAssetCount: $unusedAssetCount,
            incompleteAssetCount: $incompleteAssetCount,
            unavailableReasons: array_values(array_unique($reasons)),
            assetResults: $assetResults,
        );
    }

    /**
     * @param  list<array{asset: Asset, financials: AssetFinancialsResult}>  $assetResults
     */
    private function reportingCurrency(array $assetResults): string
    {
        if ($assetResults === []) {
            return settings('company.base_currency', 'GBP');
        }

        return $assetResults[0]['financials']->currencyCode;
    }

    private function isEligibleAsset(Asset $asset): bool
    {
        $stockLevel = $asset->currentStockLevel;

        return AssetOwnershipType::tryFrom((string) $asset->getRawOriginal('ownership_type')) === AssetOwnershipType::CompanyOwned
            && $stockLevel !== null
            && $stockLevel->isSerialised()
            && StockCategory::tryFrom((int) $stockLevel->getRawOriginal('stock_category')) === StockCategory::SerialisedStock
            && ! $stockLevel->isVirtualAsset();
    }

    private function ratio(int $numerator, int $denominator): string
    {
        return BigDecimal::of((string) $numerator)
            ->dividedBy((string) $denominator, 10, RoundingMode::HALF_UP)
            ->toScale(10, RoundingMode::HALF_UP)
            ->__toString();
    }
}
