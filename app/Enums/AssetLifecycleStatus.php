<?php

namespace App\Enums;

enum AssetLifecycleStatus: string
{
    case Active = 'active';
    case Missing = 'missing';
    case Lost = 'lost';
    case Retired = 'retired';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Missing => 'Missing',
            self::Lost => 'Lost',
            self::Retired => 'Retired',
        };
    }
}
