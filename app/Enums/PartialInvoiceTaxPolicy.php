<?php

namespace App\Enums;

enum PartialInvoiceTaxPolicy: string
{
    case Proportional = 'proportional';
    case Full = 'full';

    public function label(): string
    {
        return match ($this) {
            self::Proportional => 'Proportional',
            self::Full => 'Full tax up-front',
        };
    }
}
