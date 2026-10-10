<?php

namespace App\Services\Assets;

use App\Enums\UtilizationCompleteness;
use Carbon\CarbonImmutable;

final readonly class AssetUtilizationResult
{
    /** @param list<string> $unavailableReasons */
    public function __construct(
        public CarbonImmutable $windowStart,
        public CarbonImmutable $windowEnd,
        public CarbonImmutable $asOf,
        public ?int $ownedDuration,
        public ?int $operationalLifetime,
        public ?int $plannableDuration,
        public int $actualRentalDuration,
        public ?int $rentalWithinPlannableDuration,
        public int $rentalParticipationCount,
        public int $maintenanceDowntime,
        public ?int $maintenanceBlockedDuration,
        public int $lifecycleDowntime,
        public ?int $lifecycleBlockedDuration,
        public ?int $idlePlannableDuration,
        public ?string $actualRentalUtilizationRatio,
        public ?string $calendarRentalUtilizationRatio,
        public ?string $maintenanceDowntimeRatio,
        public ?string $lifecycleDowntimeRatio,
        public ?string $idleRatio,
        public UtilizationCompleteness $completeness,
        public array $unavailableReasons = [],
    ) {}
}
