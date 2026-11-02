<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Enums;

enum QuotationDealPriceMode: string
{
    case FlatHeadline = 'flat_headline';
    case Proportional = 'proportional';
}
