<?php

declare(strict_types=1);

namespace App\Domain\Tax\Enums;

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
