<?php

use App\Enums\TaxBuyerType;

it('exposes the four spec buyer types with canonical string values', function () {
    expect(TaxBuyerType::Business->value)->toBe('business')
        ->and(TaxBuyerType::Individual->value)->toBe('individual')
        ->and(TaxBuyerType::Government->value)->toBe('government')
        ->and(TaxBuyerType::Charity->value)->toBe('charity')
        ->and(TaxBuyerType::cases())->toHaveCount(4);
});

dataset('legacy buyer type aliases', [
    'b2b to business' => ['b2b', 'business'],
    'B2B to business' => ['B2B', 'business'],
    'b2c to individual' => ['b2c', 'individual'],
    'B2C to individual' => ['B2C', 'individual'],
]);

it('coerces legacy b2b and b2c aliases to the new canonical values', function (string $input, string $expected) {
    expect(TaxBuyerType::coerce($input))->toBe($expected);
})->with('legacy buyer type aliases');

it('passes through canonical buyer type values unchanged', function () {
    foreach (TaxBuyerType::cases() as $case) {
        expect(TaxBuyerType::coerce($case->value))->toBe($case->value);
    }
});

it('returns unknown values untouched for downstream validation to reject', function () {
    expect(TaxBuyerType::coerce('wholesale'))->toBe('wholesale');
});

it('includes legacy aliases in validation values for backward-compatible input', function () {
    expect(TaxBuyerType::validationValues())
        ->toContain('business', 'individual', 'government', 'charity', 'b2b', 'b2c');
});

it('labels each buyer type for admin UI display', function () {
    expect(TaxBuyerType::Business->label())->toBe('Business')
        ->and(TaxBuyerType::Individual->label())->toBe('Individual')
        ->and(TaxBuyerType::Government->label())->toBe('Government')
        ->and(TaxBuyerType::Charity->label())->toBe('Charity');
});
