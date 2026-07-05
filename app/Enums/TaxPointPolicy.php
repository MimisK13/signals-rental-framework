<?php

namespace App\Enums;

enum TaxPointPolicy: string
{
    case InvoiceDate = 'invoice_date';
    case SupplyDate = 'supply_date';
}
