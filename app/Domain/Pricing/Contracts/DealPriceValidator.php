<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Contracts;

use App\Domain\Projects\Opportunities\Models\Opportunity;
use App\Domain\Projects\Opportunities\Models\OpportunityItem;

interface DealPriceValidator
{
    public function key(): string;

    public function validate(Opportunity $opportunity, int $dealTotalMinor, ?OpportunityItem $groupItem = null): void;
}
