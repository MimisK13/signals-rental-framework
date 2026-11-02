<?php

declare(strict_types=1);

namespace App\Domain\Assets\Enums;

enum AssetRentalRevenueAttributionStatus: string
{
    case Available = 'available';

    case Ambiguous = 'ambiguous';

    case NotApplicable = 'not_applicable';
}
