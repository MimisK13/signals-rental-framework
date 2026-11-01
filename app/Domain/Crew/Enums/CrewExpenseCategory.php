<?php

declare(strict_types=1);

namespace App\Domain\Crew\Enums;

enum CrewExpenseCategory: string
{
    case Travel = 'travel';
    case Accommodation = 'accommodation';
    case PerDiem = 'per_diem';
    case Meals = 'meals';
    case Parking = 'parking';
    case Equipment = 'equipment';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Travel => 'Travel',
            self::Accommodation => 'Accommodation',
            self::PerDiem => 'Per Diem',
            self::Meals => 'Meals',
            self::Parking => 'Parking',
            self::Equipment => 'Equipment',
            self::Other => 'Other',
        };
    }
}
