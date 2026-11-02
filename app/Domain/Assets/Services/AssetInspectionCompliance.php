<?php

declare(strict_types=1);

namespace App\Domain\Assets\Services;

use App\Domain\Assets\Enums\InspectionComplianceState;
use App\Domain\Assets\Models\AssetInspection;
use Carbon\CarbonImmutable;

final readonly class AssetInspectionCompliance
{
    public function __construct(
        public InspectionComplianceState $state,
        public ?AssetInspection $latestInspection,
        public ?AssetInspection $lastCompletedInspection,
        public ?CarbonImmutable $nextDueAt,
    ) {}
}
