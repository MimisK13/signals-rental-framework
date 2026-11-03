<?php

namespace App\Contracts\Pricing;

use App\Domain\Projects\Opportunities\Models\Opportunity;
use App\Domain\Projects\Opportunities\Models\OpportunityItem;

interface DealPriceValidator
{
    public function key(): string;

    public function validate(Opportunity $opportunity, int $dealTotalMinor, ?OpportunityItem $groupItem = null): void;
}
