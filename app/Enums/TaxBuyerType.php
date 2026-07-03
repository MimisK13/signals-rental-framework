<?php

namespace App\Enums;

enum TaxBuyerType: string
{
    case B2b = 'b2b';
    case B2c = 'b2c';

    public function label(): string
    {
        return match ($this) {
            self::B2b => 'B2B',
            self::B2c => 'B2C',
        };
    }
}
