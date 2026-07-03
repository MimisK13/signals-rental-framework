<?php

use App\Enums\PricingRoundingDirection;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\OrganisationTaxClass;
use App\Models\ProductTaxClass;
use App\Models\TaxRate;
use App\Models\TaxRule;
use App\Services\Opportunities\OpportunityTotalsCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function discountCalculator(): OpportunityTotalsCalculator
{
    return app(OpportunityTotalsCalculator::class);
}

/**
 * Invoke the private applyDiscount() helper for direct regression coverage.
 */
function applyDiscountMinor(int $netMinor, ?string $discountPercent, string $currencyCode = 'GBP'): int
{
    $method = new ReflectionMethod(OpportunityTotalsCalculator::class, 'applyDiscount');
    $method->setAccessible(true);

    return $method->invoke(discountCalculator(), $netMinor, $discountPercent, $currencyCode);
}

/**
 * Legacy HALF_UP discount rounding used before P1.
 */
function legacyApplyDiscount(int $netMinor, ?string $discountPercent): int
{
    if ($discountPercent === null || bccomp($discountPercent, '0', 10) === 0) {
        return $netMinor;
    }

    $rawDiscount = bcdiv(bcmul((string) $netMinor, $discountPercent, 10), '100', 10);

    $discount = (int) bcadd($rawDiscount, str_starts_with($rawDiscount, '-') ? '-0.5' : '0.5', 0);

    return $netMinor - $discount;
}

describe('applyDiscount half_up regression', function () {
    beforeEach(function () {
        settings()->set('pricing.rounding_direction', PricingRoundingDirection::HalfUp->value);
    });

    it('matches legacy output byte-for-byte across representative net/discount pairs', function (int $netMinor, ?string $discountPercent) {
        expect(applyDiscountMinor($netMinor, $discountPercent))
            ->toBe(legacyApplyDiscount($netMinor, $discountPercent));
    })->with([
        'no discount' => [10_000, null],
        'zero discount' => [10_000, '0'],
        'whole percent' => [10_000, '10'],
        'fractional percent' => [10_000, '33.33'],
        'half-boundary net' => [5, '50'],
        'small net' => [1, '50'],
        'negative discount path' => [100, '-10'],
    ]);

    it('preserves unchanged nets for null and zero discounts', function () {
        expect(applyDiscountMinor(12_345, null))->toBe(12_345)
            ->and(applyDiscountMinor(12_345, '0'))->toBe(12_345);
    });
});

describe('applyDiscount bankers rounding', function () {
    beforeEach(function () {
        settings()->set('pricing.rounding_direction', PricingRoundingDirection::Bankers->value);
    });

    it('uses half-even at the .5 minor-unit boundary', function () {
        // 5 * 50% = 2.5 discount → bankers rounds to 2, net 3.
        expect(applyDiscountMinor(5, '50'))->toBe(3)
            ->and(legacyApplyDiscount(5, '50'))->toBe(2);
    });

    it('changes line totals when bankers differs from half_up', function () {
        $member = Member::factory()->organisation()->create();
        $orgClass = OrganisationTaxClass::factory()->create();
        $productClass = ProductTaxClass::factory()->create(['is_default' => true]);
        $taxRate = TaxRate::factory()->create(['name' => 'Standard', 'rate' => '0.0000', 'is_active' => true]);
        TaxRule::factory()->create([
            'organisation_tax_class_id' => $orgClass->id,
            'product_tax_class_id' => $productClass->id,
            'tax_rate_id' => $taxRate->id,
            'is_active' => true,
        ]);
        $member->forceFill(['sale_tax_class_id' => $orgClass->id])->saveQuietly();

        $opportunity = Opportunity::factory()->create([
            'member_id' => $member->id,
            'currency_code' => 'GBP',
            'prices_include_tax' => false,
        ]);

        $item = OpportunityItem::factory()->sale()->create([
            'opportunity_id' => $opportunity->id,
            'quantity' => '1',
            'unit_price' => 5,
            'discount_percent' => '50',
            'is_optional' => false,
        ]);

        settings()->set('pricing.rounding_direction', PricingRoundingDirection::HalfUp->value);
        discountCalculator()->recalculateItem($item->fresh(), manualUnitPrice: 5);
        $halfUpTotal = $item->fresh()->total;

        settings()->set('pricing.rounding_direction', PricingRoundingDirection::Bankers->value);
        discountCalculator()->recalculateItem($item->fresh(), manualUnitPrice: 5);
        $bankersTotal = $item->fresh()->total;

        expect($halfUpTotal)->toBe(2)
            ->and($bankersTotal)->toBe(3);
    });
});

describe('applyDiscount fire-time settings', function () {
    it('honours a mid-flight rounding_direction change on the same calculator instance', function () {
        $calculator = app(OpportunityTotalsCalculator::class);
        $method = new ReflectionMethod(OpportunityTotalsCalculator::class, 'applyDiscount');
        $method->setAccessible(true);

        settings()->set('pricing.rounding_direction', PricingRoundingDirection::HalfUp->value);
        $halfUpNet = $method->invoke($calculator, 5, '50', 'GBP');

        settings()->set('pricing.rounding_direction', PricingRoundingDirection::Bankers->value);
        $bankersNet = $method->invoke($calculator, 5, '50', 'GBP');

        expect($halfUpNet)->toBe(2)
            ->and($bankersNet)->toBe(3);
    });
});
