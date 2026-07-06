<?php

namespace App\Contracts\Pricing;

use App\Models\Opportunity;
use App\Models\OpportunityItem;
use Carbon\CarbonInterface;

/**
 * Plugin-registered discount source participating in highest-wins resolution.
 */
interface DiscountSource
{
    public function key(): string;

    public function discountPercentForItem(
        OpportunityItem $item,
        Opportunity $opportunity,
        CarbonInterface $at,
    ): ?string;
}
