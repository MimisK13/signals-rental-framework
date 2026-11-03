<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Contracts;

use App\Domain\Projects\Opportunities\Models\Opportunity;
use App\Domain\Projects\Opportunities\Models\OpportunityItem;
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
