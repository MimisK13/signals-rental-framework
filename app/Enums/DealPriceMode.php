<?php

namespace App\Enums;

enum DealPriceMode: string
{
    case FlatHeadline = 'flat_headline';
    case Proportional = 'proportional';

    public function label(): string
    {
        return match ($this) {
            self::FlatHeadline => 'Flat headline override',
            self::Proportional => 'Proportional line distribution',
        };
    }
}
