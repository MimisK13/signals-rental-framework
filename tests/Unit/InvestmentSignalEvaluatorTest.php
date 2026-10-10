<?php

use App\Enums\AssetLifecycleStatus;
use App\Enums\AssetRentalRevenueAttributionStatus;
use App\Enums\InvestmentGuidance;
use App\Enums\InvestmentIntelligenceCompleteness;
use App\Enums\InvestmentSignalType;
use App\Enums\UtilizationCompleteness;
use App\Services\Assets\AssetFinancialsResult;
use App\Services\Assets\AssetUtilizationResult;
use App\Services\Assets\ProductUtilizationResult;
use App\Services\Investment\InvestmentSignalEvaluator;
use Carbon\CarbonImmutable;
use Tests\TestCase;

uses(TestCase::class);

function evaluatorFinancials(string $recovery): AssetFinancialsResult
{
    $acquisition = 10000;
    $netIncome = (int) round($acquisition * (float) $recovery);

    return new AssetFinancialsResult(
        confirmedAttributedRentalRevenue: $netIncome,
        revenueStatus: AssetRentalRevenueAttributionStatus::Available,
        revenueComplete: true,
        operatingExpenses: 0,
        netRentalIncome: $netIncome,
        costRecoveryRatio: $recovery,
        remainingToBreakEven: max(0, $acquisition - $netIncome),
        investmentProfit: $netIncome - $acquisition,
        investmentRoi: (string) ((float) $recovery - 1),
        rentalCount: 3,
        completedRentalCount: 3,
        actualRentalDays: 30,
        currencyCode: 'GBP',
    );
}

function evaluatorUtilization(string $utilization = '0.50', string $maintenance = '0.00', int $participations = 3): AssetUtilizationResult
{
    return new AssetUtilizationResult(
        windowStart: CarbonImmutable::parse('2025-01-01', 'UTC'),
        windowEnd: CarbonImmutable::parse('2026-01-01', 'UTC'),
        asOf: CarbonImmutable::parse('2026-01-01', 'UTC'),
        ownedDuration: 31536000,
        operationalLifetime: 31536000,
        plannableDuration: 31536000,
        actualRentalDuration: (int) (31536000 * (float) $utilization),
        rentalWithinPlannableDuration: (int) (31536000 * (float) $utilization),
        rentalParticipationCount: $participations,
        maintenanceDowntime: (int) (31536000 * (float) $maintenance),
        maintenanceBlockedDuration: (int) (31536000 * (float) $maintenance),
        lifecycleDowntime: 0,
        lifecycleBlockedDuration: 0,
        idlePlannableDuration: (int) (31536000 * (1 - (float) $utilization)),
        actualRentalUtilizationRatio: $utilization,
        calendarRentalUtilizationRatio: $utilization,
        maintenanceDowntimeRatio: $maintenance,
        lifecycleDowntimeRatio: '0',
        idleRatio: (string) (1 - (float) $utilization),
        completeness: UtilizationCompleteness::Complete,
    );
}

function evaluatorProductUtilization(string $utilization): ProductUtilizationResult
{
    return new ProductUtilizationResult(
        windowStart: CarbonImmutable::parse('2025-01-01', 'UTC'),
        windowEnd: CarbonImmutable::parse('2026-01-01', 'UTC'),
        asOf: CarbonImmutable::parse('2026-01-01', 'UTC'),
        assetCount: 2,
        assetsWithCompleteData: 2,
        assetsWithIncompleteData: 0,
        totalOperationalLifetime: 31536000,
        totalPlannableDuration: 31536000,
        totalActualRentalDuration: (int) (31536000 * (float) $utilization),
        totalRentalWithinPlannableDuration: (int) (31536000 * (float) $utilization),
        totalMaintenanceDowntime: 0,
        totalMaintenanceBlockedDuration: 0,
        totalLifecycleDowntime: 0,
        totalLifecycleBlockedDuration: 0,
        totalIdlePlannableDuration: (int) (31536000 * (1 - (float) $utilization)),
        actualRentalUtilizationRatio: $utilization,
        calendarRentalUtilizationRatio: $utilization,
        maintenanceDowntimeRatio: '0',
        lifecycleDowntimeRatio: '0',
        idleRatio: (string) (1 - (float) $utilization),
        rentalParticipationCount: 3,
        completeness: UtilizationCompleteness::Complete,
    );
}

it('emits exact asset financial boundary signals without overlap', function (): void {
    $evaluator = app(InvestmentSignalEvaluator::class);
    $from = CarbonImmutable::parse('2025-01-01', 'UTC');
    $until = CarbonImmutable::parse('2026-01-01', 'UTC');

    $signals = $evaluator->assetSignals(
        evaluatorFinancials('1.00'),
        evaluatorUtilization('0.50'),
        AssetLifecycleStatus::Active,
        CarbonImmutable::parse('2020-01-01', 'UTC'),
        $from,
        $until,
        $until,
    );

    expect(collect($signals)->pluck('type')->all())->toContain(InvestmentSignalType::CostRecovered)
        ->and(collect($signals)->pluck('type')->all())->not->toContain(InvestmentSignalType::NearBreakEven);
});

it('allows a mature zero-rental asset to trigger low utilization', function (): void {
    $signals = app(InvestmentSignalEvaluator::class)->assetSignals(
        evaluatorFinancials('0.05'),
        evaluatorUtilization('0.00', '0.00', 0),
        AssetLifecycleStatus::Active,
        CarbonImmutable::parse('2020-01-01', 'UTC'),
        CarbonImmutable::parse('2025-01-01', 'UTC'),
        CarbonImmutable::parse('2026-01-01', 'UTC'),
        CarbonImmutable::parse('2026-01-01', 'UTC'),
    );

    expect(collect($signals)->pluck('type')->all())->toContain(InvestmentSignalType::LowUtilization);
});

it('emits maintenance burden and conservative retirement review', function (): void {
    $signals = app(InvestmentSignalEvaluator::class)->assetSignals(
        evaluatorFinancials('1.00'),
        evaluatorUtilization('0.10', '0.15'),
        AssetLifecycleStatus::Active,
        CarbonImmutable::parse('2020-01-01', 'UTC'),
        CarbonImmutable::parse('2025-01-01', 'UTC'),
        CarbonImmutable::parse('2026-01-01', 'UTC'),
        CarbonImmutable::parse('2026-01-01', 'UTC'),
    );

    expect(collect($signals)->pluck('type')->all())->toContain(InvestmentSignalType::HighMaintenanceBurden)
        ->and(collect($signals)->pluck('type')->all())->toContain(InvestmentSignalType::RetirementReview)
        ->and(app(InvestmentSignalEvaluator::class)->guidance($signals, true))->toBe([InvestmentGuidance::ReviewRetirement]);
});

it('does not recommend retirement for non-active assets', function (): void {
    $signals = app(InvestmentSignalEvaluator::class)->assetSignals(
        evaluatorFinancials('1.00'),
        evaluatorUtilization('0.10', '0.15'),
        AssetLifecycleStatus::Retired,
        CarbonImmutable::parse('2020-01-01', 'UTC'),
        CarbonImmutable::parse('2025-01-01', 'UTC'),
        CarbonImmutable::parse('2026-01-01', 'UTC'),
        CarbonImmutable::parse('2026-01-01', 'UTC'),
    );

    expect(collect($signals)->pluck('type')->all())->not->toContain(InvestmentSignalType::RetirementReview);
});

it('requires reliable pressure evidence before expansion review', function (): void {
    $evaluator = app(InvestmentSignalEvaluator::class);
    $from = CarbonImmutable::parse('2025-01-01', 'UTC');
    $until = CarbonImmutable::parse('2026-01-01', 'UTC');

    $signals = $evaluator->productSignals(
        evaluatorProductUtilization('0.85'),
        null,
        InvestmentIntelligenceCompleteness::Partial,
        null,
        InvestmentIntelligenceCompleteness::Insufficient,
        $from,
        $until,
    );

    expect(collect($signals)->pluck('type')->all())->toContain(InvestmentSignalType::HighUtilization)
        ->and(collect($signals)->pluck('type')->all())->not->toContain(InvestmentSignalType::ExpansionReview);
});

it('does not expand a low-utilization fleet because of sub-hire alone', function (): void {
    $signals = app(InvestmentSignalEvaluator::class)->productSignals(
        evaluatorProductUtilization('0.15'),
        '0.40',
        InvestmentIntelligenceCompleteness::Complete,
        null,
        InvestmentIntelligenceCompleteness::Insufficient,
        CarbonImmutable::parse('2025-01-01', 'UTC'),
        CarbonImmutable::parse('2026-01-01', 'UTC'),
    );

    expect(collect($signals)->pluck('type')->all())->toContain(InvestmentSignalType::LowUtilization)
        ->and(collect($signals)->pluck('type')->all())->toContain(InvestmentSignalType::HighSubhireDependency)
        ->and(collect($signals)->pluck('type')->all())->not->toContain(InvestmentSignalType::ExpansionReview);
});

it('requires known absence of pressure before excess capacity guidance', function (): void {
    $signals = app(InvestmentSignalEvaluator::class)->productSignals(
        evaluatorProductUtilization('0.15'),
        null,
        InvestmentIntelligenceCompleteness::Partial,
        null,
        InvestmentIntelligenceCompleteness::Insufficient,
        CarbonImmutable::parse('2025-01-01', 'UTC'),
        CarbonImmutable::parse('2026-01-01', 'UTC'),
    );

    expect(collect($signals)->pluck('type')->all())->toContain(InvestmentSignalType::LowUtilization)
        ->and(collect($signals)->pluck('type')->all())->not->toContain(InvestmentSignalType::ExcessCapacity);
});

it('rejects invalid policy configuration clearly', function (): void {
    config(['investment-intelligence.low_utilization_ratio' => 0.80]);

    expect(fn () => new InvestmentSignalEvaluator)->toThrow(InvalidArgumentException::class);
});
