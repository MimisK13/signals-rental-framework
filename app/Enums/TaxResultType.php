<?php

namespace App\Enums;

enum TaxResultType: string
{
    case Standard = 'standard';
    case ReverseCharge = 'reverse_charge';
    case ZeroRated = 'zero_rated';
    case Exempt = 'exempt';
    case OutsideScope = 'outside_scope';

    public function label(): string
    {
        return match ($this) {
            self::Standard => 'Standard',
            self::ReverseCharge => 'Reverse charge',
            self::ZeroRated => 'Zero rated',
            self::Exempt => 'Exempt',
            self::OutsideScope => 'Outside scope',
        };
    }
}
