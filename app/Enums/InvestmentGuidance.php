<?php

namespace App\Enums;

enum InvestmentGuidance: string
{
    case NoAction = 'no_action';
    case Monitor = 'monitor';
    case ReviewExpansion = 'review_expansion';
    case ReviewFleetSize = 'review_fleet_size';
    case ReviewRetirement = 'review_retirement';

    public function label(): string
    {
        return match ($this) {
            self::NoAction => 'No Action',
            self::Monitor => 'Monitor',
            self::ReviewExpansion => 'Review Fleet Expansion',
            self::ReviewFleetSize => 'Review Fleet Size',
            self::ReviewRetirement => 'Review Retirement',
        };
    }
}
