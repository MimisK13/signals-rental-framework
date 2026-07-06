<?php

namespace App\Enums;

enum TaxUnresolvedZoneFallback: string
{
    case SellerDomestic = 'seller_domestic';

    public function label(): string
    {
        return match ($this) {
            self::SellerDomestic => 'Treat as seller domestic',
        };
    }
}
