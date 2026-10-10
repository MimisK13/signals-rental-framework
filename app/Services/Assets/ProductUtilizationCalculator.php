<?php

namespace App\Services\Assets;

use App\Enums\AssetOwnershipType;
use App\Enums\StockMethod;
use App\Enums\UtilizationCompleteness;
use App\Models\Asset;
use App\Models\Product;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;

final class ProductUtilizationCalculator
{
    public function __construct(private readonly AssetUtilizationCalculator $assetCalculator) {}

    public function calculate(Product $product, CarbonImmutable $from, CarbonImmutable $until, CarbonImmutable $asOf): ProductUtilizationResult
    {
        if ($from->greaterThanOrEqualTo($until)) {
            throw new \InvalidArgumentException('The utilization window must end after it starts.');
        }

        $assets = $product->assets()
            ->where('ownership_type', AssetOwnershipType::CompanyOwned->value)
            ->whereHas('product', fn ($query) => $query->where('stock_method', StockMethod::Serialised->value))
            ->with(['product', 'usages', 'repairs', 'lifecycleEvents'])
            ->get();

        $results = $assets
            ->map(fn (Asset $asset): AssetUtilizationResult => $this->assetCalculator->calculate($asset, $from, $until, $asOf))
            ->values();

        $completeResults = $results->filter(fn (AssetUtilizationResult $result): bool => $result->completeness === UtilizationCompleteness::Complete);
        $incompleteResults = $results->reject(fn (AssetUtilizationResult $result): bool => $result->completeness === UtilizationCompleteness::Complete);
        $reasons = $results->flatMap(fn (AssetUtilizationResult $result): array => $result->unavailableReasons)->unique()->values()->all();
        $allComplete = $results->count() === $completeResults->count();

        $totalOperational = $results->sum(fn (AssetUtilizationResult $result): int => $result->operationalLifetime ?? 0);
        $totalPlannable = $completeResults->sum(fn (AssetUtilizationResult $result): int => $result->plannableDuration ?? 0);
        $totalRental = $results->sum(fn (AssetUtilizationResult $result): int => $result->actualRentalDuration);
        $totalRentalWithin = $completeResults->sum(fn (AssetUtilizationResult $result): int => $result->rentalWithinPlannableDuration ?? 0);
        $totalMaintenance = $results->sum(fn (AssetUtilizationResult $result): int => $result->maintenanceDowntime);
        $totalMaintenanceBlocked = $completeResults->sum(fn (AssetUtilizationResult $result): int => $result->maintenanceBlockedDuration ?? 0);
        $totalLifecycle = $results->sum(fn (AssetUtilizationResult $result): int => $result->lifecycleDowntime);
        $totalLifecycleBlocked = $completeResults->sum(fn (AssetUtilizationResult $result): int => $result->lifecycleBlockedDuration ?? 0);
        $totalIdle = $completeResults->sum(fn (AssetUtilizationResult $result): int => $result->idlePlannableDuration ?? 0);
        $totalParticipation = $results->sum(fn (AssetUtilizationResult $result): int => $result->rentalParticipationCount);
        $hasDenominator = $totalPlannable > 0 && $totalOperational > 0;
        $ratiosAvailable = $allComplete && $hasDenominator;

        if (! $hasDenominator) {
            $reasons[] = 'no_plannable_time';
        }

        $completeness = $ratiosAvailable
            ? UtilizationCompleteness::Complete
            : ($results->isEmpty() || ! $hasDenominator ? UtilizationCompleteness::Unavailable : UtilizationCompleteness::Partial);

        return new ProductUtilizationResult(
            windowStart: $from->utc(),
            windowEnd: $until->utc(),
            asOf: $asOf->utc(),
            assetCount: $results->count(),
            assetsWithCompleteData: $completeResults->count(),
            assetsWithIncompleteData: $incompleteResults->count(),
            totalOperationalLifetime: $totalOperational,
            totalPlannableDuration: $totalPlannable,
            totalActualRentalDuration: $totalRental,
            totalRentalWithinPlannableDuration: $totalRentalWithin,
            totalMaintenanceDowntime: $totalMaintenance,
            totalMaintenanceBlockedDuration: $totalMaintenanceBlocked,
            totalLifecycleDowntime: $totalLifecycle,
            totalLifecycleBlockedDuration: $totalLifecycleBlocked,
            totalIdlePlannableDuration: $totalIdle,
            actualRentalUtilizationRatio: $ratiosAvailable ? $this->ratio($totalRentalWithin, $totalPlannable) : null,
            calendarRentalUtilizationRatio: $ratiosAvailable ? $this->ratio($totalRental, $totalOperational) : null,
            maintenanceDowntimeRatio: $ratiosAvailable ? $this->ratio($totalMaintenanceBlocked, $totalOperational) : null,
            lifecycleDowntimeRatio: $ratiosAvailable ? $this->ratio($totalLifecycleBlocked, $totalOperational) : null,
            idleRatio: $ratiosAvailable ? $this->ratio($totalIdle, $totalPlannable) : null,
            rentalParticipationCount: $totalParticipation,
            completeness: $completeness,
            unavailableReasons: array_values(array_unique($reasons)),
        );
    }

    private function ratio(int $numerator, int $denominator): string
    {
        return BigDecimal::of((string) $numerator)
            ->dividedBy((string) $denominator, 10, RoundingMode::HALF_UP)
            ->toScale(10, RoundingMode::HALF_UP)
            ->__toString();
    }
}
