<?php

namespace App\Services\Assets;

use App\Models\Asset;
use App\Models\AssetExpense;
use App\Models\AssetUsage;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class AssetFinancialsCalculator
{
    private const string RevenueAmbiguous = 'revenue_ambiguous';

    private const string RevenueCurrencyUnavailable = 'revenue_currency_unavailable';

    private const string ExpenseIncomplete = 'expense_incomplete';

    private const string ExpenseCurrencyUnavailable = 'expense_currency_unavailable';

    private const string AcquisitionUnknown = 'acquisition_unknown';

    private const string AcquisitionInvalid = 'acquisition_invalid';

    private const string UsageIncomplete = 'usage_incomplete';

    public function __construct(
        private readonly AssetRentalRevenueAttributor $revenueAttributor,
    ) {}

    public function calculate(Asset $asset, ?Carbon $asOf = null): AssetFinancialsResult
    {
        $asset->loadMissing(['expenses', 'usages']);

        $reportingCurrency = $asset->base_currency_code ?: settings('company.base_currency', 'GBP');
        $revenue = $this->revenueAttributor->forAsset($asset);
        $reasons = [];

        $confirmedRevenue = 0;
        $revenueComplete = true;

        if ($revenue->isAmbiguous()) {
            $revenueComplete = false;
            $reasons[] = self::RevenueAmbiguous;
        } elseif ($revenue->isAvailable()) {
            if ($revenue->currencyCode === null || $revenue->currencyCode !== $reportingCurrency) {
                $revenueComplete = false;
                $reasons[] = self::RevenueCurrencyUnavailable;
            } else {
                $confirmedRevenue = $revenue->total() ?? 0;
            }
        }

        [$operatingExpenses, $expenseReasons] = $this->operatingExpenses($asset->expenses, $reportingCurrency);
        $reasons = [...$reasons, ...$expenseReasons];

        $netRentalIncome = null;

        if ($revenueComplete && $operatingExpenses !== null) {
            $netRentalIncome = $confirmedRevenue - $operatingExpenses;
        }

        $baseAcquisitionAmount = $asset->base_acquisition_amount;
        $acquisitionIsValid = true;

        if ($baseAcquisitionAmount === null) {
            $acquisitionIsValid = false;
            $reasons[] = self::AcquisitionUnknown;
        } elseif ($baseAcquisitionAmount <= 0) {
            $acquisitionIsValid = false;
            $reasons[] = self::AcquisitionInvalid;
        }

        $costRecoveryRatio = null;
        $remainingToBreakEven = null;
        $investmentProfit = null;
        $investmentRoi = null;

        if ($netRentalIncome !== null && $acquisitionIsValid) {
            $costRecoveryRatio = $this->ratio($netRentalIncome, $baseAcquisitionAmount);
            $remainingToBreakEven = max($baseAcquisitionAmount - $netRentalIncome, 0);
            $investmentProfit = $netRentalIncome - $baseAcquisitionAmount;
            $investmentRoi = $this->ratio($investmentProfit, $baseAcquisitionAmount);
        }

        [$actualRentalDays, $usageIsComplete] = $this->actualRentalDays(
            $asset->usages,
            $asOf ? Carbon::parse($asOf) : Carbon::now(),
        );

        if (! $usageIsComplete) {
            $reasons[] = self::UsageIncomplete;
        }

        return new AssetFinancialsResult(
            confirmedAttributedRentalRevenue: $confirmedRevenue,
            revenueStatus: $revenue->status,
            revenueComplete: $revenueComplete,
            operatingExpenses: $operatingExpenses,
            netRentalIncome: $netRentalIncome,
            costRecoveryRatio: $costRecoveryRatio,
            remainingToBreakEven: $remainingToBreakEven,
            investmentProfit: $investmentProfit,
            investmentRoi: $investmentRoi,
            rentalCount: $asset->usages->count(),
            completedRentalCount: $asset->usages->whereNotNull('returned_at')->count(),
            actualRentalDays: $actualRentalDays,
            currencyCode: $reportingCurrency,
            unavailableReasons: array_values(array_unique($reasons)),
        );
    }

    /**
     * @param  Collection<int, AssetExpense>  $expenses
     * @return array{0: ?int, 1: list<string>}
     */
    private function operatingExpenses(Collection $expenses, string $reportingCurrency): array
    {
        $total = 0;
        $reasons = [];

        foreach ($expenses as $expense) {
            $baseAmount = $expense->getRawOriginal('base_amount');
            $baseCurrencyCode = $expense->getRawOriginal('base_currency_code');

            if ($baseAmount === null || $baseAmount === '') {
                $reasons[] = self::ExpenseIncomplete;

                continue;
            }

            if ($baseCurrencyCode === null || $baseCurrencyCode === '' || $baseCurrencyCode !== $reportingCurrency) {
                $reasons[] = self::ExpenseCurrencyUnavailable;

                continue;
            }

            $total += (int) $baseAmount;
        }

        if ($reasons !== []) {
            return [null, array_values(array_unique($reasons))];
        }

        return [$total, []];
    }

    /**
     * @param  Collection<int, AssetUsage>  $usages
     * @return array{0: ?int, 1: bool}
     */
    private function actualRentalDays(Collection $usages, Carbon $asOf): array
    {
        $total = 0;

        foreach ($usages as $usage) {
            $dispatchedAt = $usage->getRawOriginal('dispatched_at');
            $returnedAt = $usage->getRawOriginal('returned_at');

            if ($dispatchedAt === null || $dispatchedAt === '') {
                return [null, false];
            }

            $start = Carbon::parse($dispatchedAt);
            $end = $returnedAt ? Carbon::parse($returnedAt) : $asOf;

            if ($end->lt($start)) {
                return [null, false];
            }

            $total += max(1, $start->diffInDays($end));
        }

        return [$total, true];
    }

    private function ratio(int $numerator, int $denominator): string
    {
        return BigDecimal::of((string) $numerator)
            ->dividedBy((string) $denominator, 10, RoundingMode::HALF_UP)
            ->toScale(10, RoundingMode::HALF_UP)
            ->__toString();
    }
}
