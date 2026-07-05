<?php

use App\Enums\PricingRoundingDirection;
use App\Models\Currency;
use App\Services\Pricing\RoundingStrategy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function roundingStrategy(): RoundingStrategy
{
    return app(RoundingStrategy::class);
}

/**
 * Legacy HALF_UP discount rounding used by OpportunityTotalsCalculator before P1.
 */
function legacyHalfUpRoundMinor(string $decimalMinorAmount): int
{
    return (int) bcadd($decimalMinorAmount, str_starts_with($decimalMinorAmount, '-') ? '-0.5' : '0.5', 0);
}

describe('fraction digits', function () {
    it('uses the currency table decimal_places when present', function () {
        Currency::factory()->create(['code' => 'JPY', 'decimal_places' => 0]);
        Currency::factory()->create(['code' => 'KWD', 'decimal_places' => 3]);

        expect(roundingStrategy()->fractionDigits('JPY'))->toBe(0)
            ->and(roundingStrategy()->fractionDigits('KWD'))->toBe(3);
    });

    it('falls back to ISO 4217 defaults when the currency is not stored', function () {
        expect(roundingStrategy()->fractionDigits('CHF'))->toBe(2);
    });
});

describe('half_up rounding (default)', function () {
    it('defaults to half_up when the setting is unset', function () {
        expect(settings('pricing.rounding_direction'))->toBe('half_up');
    });

    it('rounds minor amounts identically to the legacy bcmath half-up path', function (string $raw, int $expected) {
        settings()->set('pricing.rounding_direction', PricingRoundingDirection::HalfUp->value);

        expect(roundingStrategy()->roundMinorAmount($raw, 'GBP'))->toBe($expected)
            ->and(legacyHalfUpRoundMinor($raw))->toBe($expected);
    })->with([
        'exact integer' => ['100.0', 100],
        'half up at .5' => ['2.5', 3],
        'half up below .5' => ['2.4', 2],
        'half up above .5' => ['2.6', 3],
        'negative half up' => ['-2.5', -3],
        'large discount slice' => ['3333.3333333333', 3333],
    ]);

    it('rounds major amounts to minor units with currency scale', function () {
        settings()->set('pricing.rounding_direction', PricingRoundingDirection::HalfUp->value);

        Currency::factory()->create(['code' => 'JPY', 'decimal_places' => 0, 'symbol' => '¥']);

        expect(roundingStrategy()->roundMajorToMinorUnits('100.5', 'JPY'))->toBe(101)
            ->and(roundingStrategy()->roundMajorToMinorUnits('10.125', 'GBP'))->toBe(1013);
    });
});

describe('bankers rounding (half-even)', function () {
    beforeEach(function () {
        settings()->set('pricing.rounding_direction', PricingRoundingDirection::Bankers->value);
    });

    it('rounds .5 toward the nearest even integer for minor amounts', function (string $raw, int $expected) {
        expect(roundingStrategy()->roundMinorAmount($raw, 'GBP'))->toBe($expected);
    })->with([
        '2.5 to even 2' => ['2.5', 2],
        '3.5 to even 4' => ['3.5', 4],
        '4.5 to even 4' => ['4.5', 4],
        '5.5 to even 6' => ['5.5', 6],
        '1.5 to even 2' => ['1.5', 2],
    ]);

    it('differs from half_up exactly at .5 boundaries', function () {
        settings()->set('pricing.rounding_direction', PricingRoundingDirection::HalfUp->value);
        $halfUp = roundingStrategy()->roundMinorAmount('2.5', 'GBP');

        settings()->set('pricing.rounding_direction', PricingRoundingDirection::Bankers->value);
        $bankers = roundingStrategy()->roundMinorAmount('2.5', 'GBP');

        expect($halfUp)->toBe(3)
            ->and($bankers)->toBe(2);
    });

    it('rounds major amounts per currency scale with half-even', function () {
        Currency::factory()->create(['code' => 'JPY', 'decimal_places' => 0, 'symbol' => '¥']);
        Currency::factory()->create(['code' => 'KWD', 'decimal_places' => 3, 'symbol' => 'KD']);

        expect(roundingStrategy()->roundMajorToMinorUnits('100.5', 'JPY'))->toBe(100)
            ->and(roundingStrategy()->roundMajorToMinorUnits('1.2345', 'KWD'))->toBe(1234);
    });
});

describe('fire-time setting resolution', function () {
    it('reads rounding_direction on each call without constructor caching', function () {
        $strategy = app(RoundingStrategy::class);

        settings()->set('pricing.rounding_direction', PricingRoundingDirection::HalfUp->value);
        expect($strategy->roundMinorAmount('2.5', 'GBP'))->toBe(3);

        settings()->set('pricing.rounding_direction', PricingRoundingDirection::Bankers->value);
        expect($strategy->roundMinorAmount('2.5', 'GBP'))->toBe(2);
    });

    it('falls back to half_up for unknown stored values', function () {
        settings()->set('pricing.rounding_direction', 'invalid-mode');

        expect(roundingStrategy()->roundMinorAmount('2.5', 'GBP'))->toBe(3);
    });
});
