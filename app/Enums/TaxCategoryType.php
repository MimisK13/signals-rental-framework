<?php

namespace App\Enums;

enum TaxCategoryType: string
{
    case Standard = 'standard';
    case Reduced = 'reduced';
    case Zero = 'zero';
    case Exempt = 'exempt';

    public function label(): string
    {
        return match ($this) {
            self::Standard => 'Standard',
            self::Reduced => 'Reduced',
            self::Zero => 'Zero rated',
            self::Exempt => 'Exempt',
        };
    }
}
