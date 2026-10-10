<?php

namespace App\Enums;

enum AssetRentalRevenueAttributionStatus: string
{
    case Available = 'available';

    case Ambiguous = 'ambiguous';

    case NotApplicable = 'not_applicable';
}
