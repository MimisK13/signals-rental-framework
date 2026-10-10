<?php

namespace App\Services\Assets;

use App\Enums\UtilizationCompleteness;
use Carbon\CarbonImmutable;

final readonly class ProductUtilizationResult
{
    /** @param list<string> $unavailableReasons */
    public function __construct(
        public CarbonImmutable $windowStart,
        public CarbonImmutable $windowEnd,
        public CarbonImmutable $asOf,
        public int $assetCount,
        public int $assetsWithCompleteData,
        public int $assetsWithIncompleteData,
        public int $totalOperationalLifetime,
        public int $totalPlannableDuration,
        public int $totalActualRentalDuration,
        public int $totalRentalWithinPlannableDuration,
        public int $totalMaintenanceDowntime,
        public int $totalMaintenanceBlockedDuration,
        public int $totalLifecycleDowntime,
        public int $totalLifecycleBlockedDuration,
        public int $totalIdlePlannableDuration,
        public ?string $actualRentalUtilizationRatio,
        public ?string $calendarRentalUtilizationRatio,
        public ?string $maintenanceDowntimeRatio,
        public ?string $lifecycleDowntimeRatio,
        public ?string $idleRatio,
        public int $rentalParticipationCount,
        public UtilizationCompleteness $completeness,
        public array $unavailableReasons = [],
    ) {}
}
