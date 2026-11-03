<?php

use App\Domain\Pricing\Services\OpportunityItemDiscountPercentFilterRegistry;
use App\Domain\Pricing\Services\OpportunityItemSurchargeAmountFilterRegistry;
use App\Domain\Projects\Opportunities\Models\OpportunityItem;

it('exposes registered discount percent filters via all()', function () {
    $registry = new OpportunityItemDiscountPercentFilterRegistry;
    $filter = static fn (OpportunityItem $item, ?string $percent): ?string => $percent;

    $registry->register('cap', $filter);

    expect($registry->all())->toBe(['cap' => $filter]);
});

it('exposes registered surcharge amount filters via all()', function () {
    $registry = new OpportunityItemSurchargeAmountFilterRegistry;
    $filter = static fn (OpportunityItem $item, string $amount, int $surchargeId): string => $amount;

    $registry->register('floor', $filter);

    expect($registry->all())->toBe(['floor' => $filter]);
});
