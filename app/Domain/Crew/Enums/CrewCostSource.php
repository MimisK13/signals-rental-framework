<?php

declare(strict_types=1);

namespace App\Domain\Crew\Enums;

enum CrewCostSource: string
{
    case RateCard = 'rate_card';
    case AssignmentSnapshot = 'assignment_snapshot';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::RateCard => 'Rate Card',
            self::AssignmentSnapshot => 'Assignment Snapshot',
            self::Manual => 'Manual',
        };
    }
}
