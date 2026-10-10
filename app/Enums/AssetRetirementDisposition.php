<?php

namespace App\Enums;

enum AssetRetirementDisposition: string
{
    case Retired = 'retired';
    case Sold = 'sold';
    case Disposed = 'disposed';

    public function label(): string
    {
        return match ($this) {
            self::Retired => 'Retired',
            self::Sold => 'Sold',
            self::Disposed => 'Disposed',
        };
    }
}
