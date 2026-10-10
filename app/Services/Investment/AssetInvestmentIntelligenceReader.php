<?php

namespace App\Services\Investment;

use App\Data\InvestmentComponentCompleteness;
use App\Enums\InvestmentIntelligenceCompleteness;
use App\Enums\UtilizationCompleteness;
use App\Models\Asset;
use App\Services\Assets\AssetFinancialsCalculator;
use App\Services\Assets\AssetFinancialsResult;
use App\Services\Assets\AssetUtilizationCalculator;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

final class AssetInvestmentIntelligenceReader
{
    public function __construct(
        private readonly AssetFinancialsCalculator $financials,
        private readonly AssetUtilizationCalculator $utilization,
        private readonly InvestmentSignalEvaluator $evaluator,
    ) {}

    public function read(Asset $asset, CarbonImmutable $from, CarbonImmutable $until, CarbonImmutable $asOf): AssetInvestmentIntelligenceResult
    {
        if ($from->greaterThanOrEqualTo($until)) {
            throw new \InvalidArgumentException('The investment window must end after it starts.');
        }

        $asset->loadMissing(['product', 'lifecycleEvents']);
        $financials = $this->financials->calculate($asset, Carbon::parse($asOf->toIso8601String()));
        $utilization = $this->utilization->calculate($asset, $from, $until, $asOf);
        $lifecycle = $this->lifecycleSummary($asset);
        $signals = $this->evaluator->assetSignals(
            $financials,
            $utilization,
            $asset->lifecycleStatus(),
            $asset->acquired_at === null ? null : CarbonImmutable::parse($asset->acquired_at)->utc(),
            $from,
            $until,
            $asOf,
        );

        $components = [
            'financial' => $financials->isFinanciallyComplete() ? InvestmentIntelligenceCompleteness::Complete : InvestmentIntelligenceCompleteness::Partial,
            'utilization' => $this->utilizationCompleteness($utilization->completeness),
            'lifecycle' => in_array('lifecycle_history_incomplete', $utilization->unavailableReasons, true)
                ? InvestmentIntelligenceCompleteness::Partial
                : InvestmentIntelligenceCompleteness::Complete,
            'externalRental' => InvestmentIntelligenceCompleteness::Insufficient,
            'shortage' => InvestmentIntelligenceCompleteness::Insufficient,
        ];

        $reasons = array_values(array_unique(array_merge($financials->unavailableReasons, $utilization->unavailableReasons)));
        $completeness = $this->completeness($components, $reasons);

        return new AssetInvestmentIntelligenceResult(
            asset: $asset,
            windowStart: $from->utc(),
            windowEnd: $until->utc(),
            asOf: $asOf->utc(),
            financials: $financials,
            utilization: $utilization,
            lifecycleSummary: $lifecycle,
            maintenanceSummary: [
                'blocked_duration' => $utilization->maintenanceBlockedDuration,
                'burden_ratio' => $utilization->maintenanceDowntimeRatio,
            ],
            lifecycleInvestmentProfit: $this->lifecycleInvestmentProfit($financials, $lifecycle),
            lifecycleRoi: $this->lifecycleRoi($financials, $lifecycle),
            signals: $signals,
            guidance: $this->evaluator->guidance($signals, true),
            completeness: $completeness,
        );
    }

    /** @return array<string, mixed> */
    private function lifecycleSummary(Asset $asset): array
    {
        $terminal = $asset->lifecycleEvents
            ->filter(fn ($event): bool => $event->hasTerminalDisposition())
            ->sortByDesc('occurred_at')
            ->first();

        return [
            'status' => $asset->lifecycleStatus()->label(),
            'terminal_disposition' => $terminal?->getRawOriginal('disposition'),
            'terminal_occurred_at' => $terminal?->occurred_at,
            'proceeds_amount' => $terminal?->proceeds_amount,
            'proceeds_currency_code' => $terminal?->proceeds_currency_code,
            'buyer_member_id' => $terminal?->buyer_member_id,
        ];
    }

    /** @param array{proceeds_amount: int|null, proceeds_currency_code: string|null} $lifecycle */
    private function lifecycleInvestmentProfit(AssetFinancialsResult $financials, array $lifecycle): ?int
    {
        if ($financials->netRentalIncome === null || $financials->investmentProfit === null || $lifecycle['proceeds_amount'] === null || strtoupper((string) $lifecycle['proceeds_currency_code']) !== strtoupper($financials->currencyCode)) {
            return null;
        }

        return $financials->investmentProfit + (int) $lifecycle['proceeds_amount'];
    }

    /** @param array{proceeds_amount: int|null, proceeds_currency_code: string|null} $lifecycle */
    private function lifecycleRoi(AssetFinancialsResult $financials, array $lifecycle): ?string
    {
        $profit = $this->lifecycleInvestmentProfit($financials, $lifecycle);
        $acquisition = $financials->netRentalIncome !== null && $financials->investmentProfit !== null
            ? $financials->netRentalIncome - $financials->investmentProfit
            : null;

        if ($profit === null || $acquisition === null || $acquisition <= 0) {
            return null;
        }

        return BigDecimal::of((string) $profit)
            ->dividedBy((string) $acquisition, 10, RoundingMode::HALF_UP)
            ->toScale(10, RoundingMode::HALF_UP)
            ->__toString();
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
