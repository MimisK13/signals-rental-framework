<?php

declare(strict_types=1);

namespace App\Domain\Tax\Enums;

enum TaxPointPolicy: string
{
    case InvoiceDate = 'invoice_date';
    case SupplyDate = 'supply_date';
}
