<?php

namespace App\Contracts\Pricing;

use App\Models\Opportunity;
use App\Models\OpportunityItem;

interface DealPriceValidator
{
    public function key(): string;

    public function validate(Opportunity $opportunity, int $dealTotalMinor, ?OpportunityItem $groupItem = null): void;
}
