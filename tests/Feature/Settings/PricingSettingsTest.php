<?php

use App\Enums\PricingRoundingDirection;
use App\Services\SettingsRegistry;
use App\Settings\PricingSettings;
use Illuminate\Support\Facades\Validator;

describe('PricingSettings definition', function () {
    it('defaults rounding_direction to half_up', function () {
        expect(settings('pricing.rounding_direction'))->toBe('half_up');
    });

    it('is registered in the settings registry', function () {
        $registry = app(SettingsRegistry::class);

        expect($registry->has('pricing'))->toBeTrue()
            ->and($registry->get('pricing'))->toBeInstanceOf(PricingSettings::class);
    });

    it('round-trips a valid rounding_direction value', function () {
        settings()->set('pricing.rounding_direction', PricingRoundingDirection::Bankers->value);

        expect(settings('pricing.rounding_direction'))->toBe('bankers');
    });

    it('rejects invalid rounding_direction values via definition rules', function () {
        $validator = Validator::make(
            ['rounding_direction' => 'truncate'],
            (new PricingSettings)->rules(),
        );

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('rounding_direction'))->toBeTrue();
    });
});

describe('PricingSettings class metadata', function () {
    it('returns pricing as the group name', function () {
        expect((new PricingSettings)->group())->toBe('pricing');
    });

    it('declares string type for rounding_direction', function () {
        expect((new PricingSettings)->types())
            ->toHaveKey('rounding_direction', 'string');
    });

    it('constrains rounding_direction to half_up or bankers', function () {
        $rules = (new PricingSettings)->rules();

        expect($rules)->toHaveKey('rounding_direction')
            ->and($rules['rounding_direction'])->toContain('required');
    });
});
