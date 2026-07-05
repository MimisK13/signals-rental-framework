<?php

use App\Enums\PricingRoundingDirection;
use App\Services\Pricing\QuantityMinorArithmetic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    settings()->set('pricing.rounding_direction', PricingRoundingDirection::HalfUp->value);
});

it('multiplies fractional quantity by unit price with half-up rounding', function () {
    $arithmetic = app(QuantityMinorArithmetic::class);

    expect($arithmetic->multiplyMinorByQuantity(10_000, '1.5', 'GBP'))->toBe(15_000)
        ->and($arithmetic->multiplyMinorByQuantity(10_000, '0.5', 'GBP'))->toBe(5_000)
        ->and($arithmetic->multiplyMinorByQuantity(10_000, '2', 'GBP'))->toBe(20_000);
});

it('detects fractional quantities for rate-engine routing', function () {
    $arithmetic = app(QuantityMinorArithmetic::class);

    expect($arithmetic->quantityHasFraction('1.5'))->toBeTrue()
        ->and($arithmetic->quantityHasFraction('2.00'))->toBeFalse()
        ->and($arithmetic->wholeUnitCountForRateEngine('1.5'))->toBe(1)
        ->and($arithmetic->wholeUnitCountForRateEngine('10'))->toBe(10);
});
