<?php

/**
 * Coverage burn-up batch 1 — pure unit tests.
 */

use App\Domain\Availability\Shortages\Models\ShortageWaitlistMonitor;
use App\Domain\Notifications\Services\RecipientResolvers\SubjectOwnerRecipientResolver;
use App\Domain\Pricing\Services\Predicates\ProductIdsPredicate;
use App\Domain\Pricing\ValueObjects\DiscountCriteriaContext;
use App\Domain\Tax\Enums\TaxBuyerType;
use App\Services\Dashboard\DashboardKpiService;
use Tests\TestCase;

uses(TestCase::class);

it('TaxBuyerType coerce maps legacy b2b and b2c aliases', function () {
    expect(TaxBuyerType::coerce('b2b'))->toBe('business')
        ->and(TaxBuyerType::coerce('B2C'))->toBe('individual');
});

it('ProductIdsPredicate matches returns true for empty product id lists', function () {
    $predicate = new ProductIdsPredicate;
    $context = new DiscountCriteriaContext(product_id: 99);

    expect($predicate->matches([], $context))->toBeTrue();
});

it('DashboardKpiService percentChange returns null when previous is zero or negative', function () {
    $service = app(DashboardKpiService::class);
    $method = new ReflectionMethod(DashboardKpiService::class, 'percentChange');
    $method->setAccessible(true);

    expect($method->invoke($service, 0, 100))->toBeNull()
        ->and($method->invoke($service, -5, 100))->toBeNull()
        ->and($method->invoke($service, 50, 75))->toBe(50.0);
});

it('SubjectOwnerRecipientResolver ownerIdForWaitlistMonitor returns null without an opportunity item', function () {
    $resolver = new SubjectOwnerRecipientResolver;
    $monitor = new ShortageWaitlistMonitor(['opportunity_item_id' => null]);

    expect((new ReflectionMethod($resolver, 'ownerIdForWaitlistMonitor'))
        ->invoke($resolver, $monitor))->toBeNull();
});
