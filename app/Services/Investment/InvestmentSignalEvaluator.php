<?php

namespace App\Services\Investment;

use App\Data\InvestmentEvidence;
use App\Data\InvestmentSignal;
use App\Enums\AssetLifecycleStatus;
use App\Enums\InvestmentGuidance;
use App\Enums\InvestmentIntelligenceCompleteness;
use App\Enums\InvestmentSignalType;
use App\Enums\UtilizationCompleteness;
use App\Services\Assets\AssetFinancialsResult;
use App\Services\Assets\AssetUtilizationResult;
use App\Services\Assets\ProductUtilizationResult;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final class InvestmentSignalEvaluator
{
    public function __construct()
    {
        $this->validateConfiguration();
    }

    /**
     * @return list<InvestmentSignal>
     */
    public function assetSignals(
        AssetFinancialsResult $financials,
        AssetUtilizationResult $utilization,
        AssetLifecycleStatus $lifecycleStatus,
        ?CarbonImmutable $acquiredAt,
        CarbonImmutable $from,
        CarbonImmutable $until,
        CarbonImmutable $asOf,
        ?string $lifecycleRoi = null,
    ): array {
        $signals = [];
        $mature = $this->assetIsMature($utilization, $acquiredAt, $from, $until, $asOf);

        if ($financials->isFinanciallyComplete() && $this->atLeast($financials->costRecoveryRatio, '1')) {
            $signals[] = $this->signal(
                InvestmentSignalType::CostRecovered,
                'Net rental income has recovered the recorded acquisition cost.',
                [
                    new InvestmentEvidence('cost_recovery_ratio', $this->percentage($financials->costRecoveryRatio), '>=', '100%', null, InvestmentIntelligenceCompleteness::Complete),
                    new InvestmentEvidence('net_rental_income', (string) $financials->netRentalIncome, null, null, 'lifetime', InvestmentIntelligenceCompleteness::Complete),
                    new InvestmentEvidence('acquisition_cost', (string) ($financials->netRentalIncome - ($financials->investmentProfit ?? 0)), null, null, 'lifetime', InvestmentIntelligenceCompleteness::Complete),
                ],
            );
        } elseif ($financials->isFinanciallyComplete() && $mature && $this->atLeast($financials->costRecoveryRatio, (string) config('investment-intelligence.near_break_even_ratio'))) {
            $signals[] = $this->signal(
                InvestmentSignalType::NearBreakEven,
                'Cost recovery is near break-even, but has not reached 100%.',
                [new InvestmentEvidence('cost_recovery_ratio', $this->percentage($financials->costRecoveryRatio), '>=', $this->percentage((string) config('investment-intelligence.near_break_even_ratio')), 'lifetime', InvestmentIntelligenceCompleteness::Complete)],
            );
        }

        $lowUtilization = false;
        if ($this->matureUtilization($utilization, $from, $until)) {
            $lowUtilization = $this->atMost($utilization->actualRentalUtilizationRatio, (string) config('investment-intelligence.low_utilization_ratio'));

            if ($lowUtilization) {
                $signals[] = $this->signal(
                    InvestmentSignalType::LowUtilization,
                    'Actual rental utilization is at or below the configured low-utilization policy.',
                    [new InvestmentEvidence('actual_rental_utilization', $this->percentage($utilization->actualRentalUtilizationRatio), '<=', $this->percentage((string) config('investment-intelligence.low_utilization_ratio')), $this->window($from, $until), InvestmentIntelligenceCompleteness::Complete)],
                );
            }
        }

        $highMaintenance = false;
        if ($this->matureUtilization($utilization, $from, $until) && $utilization->maintenanceDowntimeRatio !== null) {
            $highMaintenance = $this->atLeast($utilization->maintenanceDowntimeRatio, (string) config('investment-intelligence.high_maintenance_burden_ratio'));

            if ($highMaintenance) {
                $signals[] = $this->signal(
                    InvestmentSignalType::HighMaintenanceBurden,
                    'Exclusive maintenance downtime consumes a material share of operational lifetime.',
                    [new InvestmentEvidence('maintenance_downtime_burden', $this->percentage($utilization->maintenanceDowntimeRatio), '>=', $this->percentage((string) config('investment-intelligence.high_maintenance_burden_ratio')), $this->window($from, $until), InvestmentIntelligenceCompleteness::Complete)],
                );
            }
        }

        if ($lifecycleStatus === AssetLifecycleStatus::Active && $mature && $lowUtilization && ($highMaintenance || $this->hasSignal($signals, InvestmentSignalType::CostRecovered))) {
            $retirementEvidence = $this->evidenceFor($signals, InvestmentSignalType::LowUtilization);

            if ($highMaintenance) {
                $retirementEvidence = array_merge($retirementEvidence, $this->evidenceFor($signals, InvestmentSignalType::HighMaintenanceBurden));
            }

            if ($this->hasSignal($signals, InvestmentSignalType::CostRecovered)) {
                $retirementEvidence = array_merge($retirementEvidence, $this->evidenceFor($signals, InvestmentSignalType::CostRecovered));
            }

            $signals[] = $this->signal(
                InvestmentSignalType::RetirementReview,
                'A mature active Asset has low utilization and additional evidence supporting a retirement review.',
                $retirementEvidence,
            );
        }

        return $signals;
    }

    /**
     * @return list<InvestmentSignal>
     */
    public function productSignals(
        ProductUtilizationResult $utilization,
        ?string $subhireCostRatio,
        InvestmentIntelligenceCompleteness $externalCompleteness,
        ?int $shortageOccurrences,
        InvestmentIntelligenceCompleteness $shortageCompleteness,
        CarbonImmutable $from,
        CarbonImmutable $until,
    ): array {
        $signals = [];
        $mature = $this->matureUtilization($utilization, $from, $until);

        if ($mature && $this->atLeast($utilization->actualRentalUtilizationRatio, (string) config('investment-intelligence.high_utilization_ratio'))) {
            $signals[] = $this->signal(
                InvestmentSignalType::HighUtilization,
                'Weighted fleet utilization exceeded the configured high-utilization policy.',
                [new InvestmentEvidence('weighted_utilization', $this->percentage($utilization->actualRentalUtilizationRatio), '>=', $this->percentage((string) config('investment-intelligence.high_utilization_ratio')), $this->window($from, $until), InvestmentIntelligenceCompleteness::Complete)],
            );
        }

        if ($mature && $this->atMost($utilization->actualRentalUtilizationRatio, (string) config('investment-intelligence.low_utilization_ratio'))) {
            $signals[] = $this->signal(
                InvestmentSignalType::LowUtilization,
                'Weighted fleet utilization is at or below the configured low-utilization policy.',
                [new InvestmentEvidence('weighted_utilization', $this->percentage($utilization->actualRentalUtilizationRatio), '<=', $this->percentage((string) config('investment-intelligence.low_utilization_ratio')), $this->window($from, $until), InvestmentIntelligenceCompleteness::Complete)],
            );
        }

        if ($externalCompleteness === InvestmentIntelligenceCompleteness::Complete && $subhireCostRatio !== null && $this->atLeast($subhireCostRatio, (string) config('investment-intelligence.high_subhire_cost_ratio'))) {
            $signals[] = $this->signal(
                InvestmentSignalType::HighSubhireDependency,
                'Actual issued supplier cost consumed a material share of comparable rental revenue.',
                [new InvestmentEvidence('subhire_cost_ratio', $this->percentage($subhireCostRatio), '>=', $this->percentage((string) config('investment-intelligence.high_subhire_cost_ratio')), $this->window($from, $until), InvestmentIntelligenceCompleteness::Complete)],
            );
        }

        if ($shortageCompleteness === InvestmentIntelligenceCompleteness::Complete && $shortageOccurrences !== null && $shortageOccurrences >= (int) config('investment-intelligence.minimum_shortage_occurrences')) {
            $signals[] = $this->signal(
                InvestmentSignalType::RepeatedShortage,
                'Reliable shortage evidence reached the configured occurrence threshold.',
                [new InvestmentEvidence('shortage_occurrences', (string) $shortageOccurrences, '>=', (string) config('investment-intelligence.minimum_shortage_occurrences'), $this->window($from, $until), InvestmentIntelligenceCompleteness::Complete)],
            );
        }

        $hasPressure = $this->hasSignal($signals, InvestmentSignalType::HighSubhireDependency) || $this->hasSignal($signals, InvestmentSignalType::RepeatedShortage);
        if ($this->hasSignal($signals, InvestmentSignalType::HighUtilization) && $hasPressure) {
            $signals[] = $this->signal(
                InvestmentSignalType::ExpansionReview,
                'High weighted utilization is supported by reliable external-capacity or shortage pressure evidence.',
                array_merge($this->evidenceFor($signals, InvestmentSignalType::HighUtilization), $this->evidenceFor($signals, InvestmentSignalType::HighSubhireDependency), $this->evidenceFor($signals, InvestmentSignalType::RepeatedShortage)),
            );
        }

        $pressureKnownAbsent = $externalCompleteness === InvestmentIntelligenceCompleteness::Complete
            && $shortageCompleteness === InvestmentIntelligenceCompleteness::Complete
            && ! $this->hasSignal($signals, InvestmentSignalType::HighSubhireDependency)
            && ! $this->hasSignal($signals, InvestmentSignalType::RepeatedShortage);

        if ($this->hasSignal($signals, InvestmentSignalType::LowUtilization) && $pressureKnownAbsent) {
            $signals[] = $this->signal(
                InvestmentSignalType::ExcessCapacity,
                'Low weighted utilization is not accompanied by reliable external-capacity or shortage pressure.',
                $this->evidenceFor($signals, InvestmentSignalType::LowUtilization),
            );
        }

        return $signals;
    }

    /**
     * @param  list<InvestmentSignal>  $signals
     * @return list<InvestmentGuidance>
     */
    public function guidance(array $signals, bool $asset): array
    {
        if ($asset && $this->hasSignal($signals, InvestmentSignalType::RetirementReview)) {
            return [InvestmentGuidance::ReviewRetirement];
        }

        if (! $asset && $this->hasSignal($signals, InvestmentSignalType::ExpansionReview)) {
            return [InvestmentGuidance::ReviewExpansion];
        }

        if (! $asset && $this->hasSignal($signals, InvestmentSignalType::ExcessCapacity)) {
            return [InvestmentGuidance::ReviewFleetSize];
        }

        $material = [InvestmentSignalType::LowUtilization, InvestmentSignalType::HighMaintenanceBurden, InvestmentSignalType::HighSubhireDependency, InvestmentSignalType::RepeatedShortage, InvestmentSignalType::HighUtilization, InvestmentSignalType::NearBreakEven];

        foreach ($material as $type) {
            if ($this->hasSignal($signals, $type)) {
                return [InvestmentGuidance::Monitor];
            }
        }

        return [InvestmentGuidance::NoAction];
    }

    public function validateConfiguration(): void
    {
        $ratios = ['high_utilization_ratio', 'low_utilization_ratio', 'near_break_even_ratio', 'high_maintenance_burden_ratio', 'high_subhire_cost_ratio'];
        foreach ($ratios as $key) {
            $value = (float) config('investment-intelligence.'.$key);
            if ($value < 0 || $value > 1) {
                throw new InvalidArgumentException("Investment intelligence ratio [{$key}] must be between 0 and 1.");
            }
        }

        if ((float) config('investment-intelligence.low_utilization_ratio') >= (float) config('investment-intelligence.high_utilization_ratio')) {
            throw new InvalidArgumentException('Investment intelligence low utilization must be below high utilization.');
        }

        if ((float) config('investment-intelligence.near_break_even_ratio') >= 1) {
            throw new InvalidArgumentException('Investment intelligence near break-even ratio must be below 1.');
        }

        foreach (['minimum_analysis_days', 'minimum_asset_plannable_hours', 'minimum_asset_rental_participations', 'minimum_shortage_occurrences'] as $key) {
            if ((int) config('investment-intelligence.'.$key) < 0) {
                throw new InvalidArgumentException("Investment intelligence minimum [{$key}] cannot be negative.");
            }
        }
    }

    private function assetIsMature(AssetUtilizationResult $utilization, ?CarbonImmutable $acquiredAt, CarbonImmutable $from, CarbonImmutable $until, CarbonImmutable $asOf): bool
    {
        return $acquiredAt !== null
            && $this->matureUtilization($utilization, $from, $until)
            && $acquiredAt->diffInDays($asOf) >= (int) config('investment-intelligence.minimum_analysis_days');
    }

    private function matureUtilization(AssetUtilizationResult|ProductUtilizationResult $utilization, CarbonImmutable $from, CarbonImmutable $until): bool
    {
        $plannable = $utilization instanceof AssetUtilizationResult
            ? $utilization->plannableDuration
            : $utilization->totalPlannableDuration;
        $participations = $utilization->rentalParticipationCount;

        return $utilization->completeness === UtilizationCompleteness::Complete
            && $from->diffInDays($until) >= (int) config('investment-intelligence.minimum_analysis_days')
            && $plannable >= (int) config('investment-intelligence.minimum_asset_plannable_hours') * 3600
            && ($participations === 0 || $participations >= (int) config('investment-intelligence.minimum_asset_rental_participations'));
    }

    /** @param list<InvestmentEvidence> $evidence */
    private function signal(InvestmentSignalType $type, string $explanation, array $evidence): InvestmentSignal
    {
        return new InvestmentSignal($type, $explanation, $evidence);
    }

    /** @param list<InvestmentSignal> $signals */
    private function hasSignal(array $signals, InvestmentSignalType $type): bool
    {
        return collect($signals)->contains(fn (InvestmentSignal $signal): bool => $signal->type === $type);
    }

    /**
     * @param  list<InvestmentSignal>  $signals
     * @return list<InvestmentEvidence>
     */
    private function evidenceFor(array $signals, InvestmentSignalType $type): array
    {
        foreach ($signals as $signal) {
            if ($signal->type === $type) {
                return $signal->evidence;
            }
        }

        return [];
    }

    private function atLeast(?string $value, string $threshold): bool
    {
        return $value !== null && BigDecimal::of($value)->isGreaterThanOrEqualTo($threshold);
    }

    private function atMost(?string $value, string $threshold): bool
    {
        return $value !== null && BigDecimal::of($value)->isLessThanOrEqualTo($threshold);
    }

    private function percentage(?string $value): string
    {
        return $value === null ? 'unavailable' : BigDecimal::of($value)->multipliedBy('100')->toScale(2, RoundingMode::HALF_UP)->__toString().'%';
    }

    private function window(CarbonImmutable $from, CarbonImmutable $until): string
    {
        return $from->toIso8601String().' → '.$until->toIso8601String();
    }
}
