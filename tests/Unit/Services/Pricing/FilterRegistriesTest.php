<?php

use App\Models\OpportunityItem;
use App\Services\Pricing\OpportunityItemDiscountPercentFilterRegistry;
use App\Services\Pricing\OpportunityItemSurchargeAmountFilterRegistry;

it('exposes registered discount percent filters via all()', function () {
    $registry = new OpportunityItemDiscountPercentFilterRegistry;
    $filter = static fn (OpportunityItem $item, ?string $percent): ?string => $percent;

    $registry->register('cap', $filter);

    expect($registry->all())->toBe(['cap' => $filter]);
});

it('exposes registered surcharge amount filters via all()', function () {
    $registry = new OpportunityItemSurchargeAmountFilterRegistry;
    $filter = static fn (OpportunityItem $item, string $amount, int $surchargeId): ?string => $amount;

    $registry->register('floor', $filter);

    expect($registry->all())->toBe(['floor' => $filter]);
});
