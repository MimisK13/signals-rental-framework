<?php

namespace App\Services\Assets;

use App\Enums\InspectionComplianceState;
use App\Models\AssetInspection;
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
