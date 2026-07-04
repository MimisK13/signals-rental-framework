<?php

use App\Enums\PricingRoundingDirection;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\OpportunityCost;
use App\Models\OpportunityItem;
use App\Models\OrganisationTaxClass;
use App\Models\Product;
use App\Models\ProductTaxClass;
use App\Models\Store;
use App\Models\TaxRate;
use App\Models\TaxRule;
use App\Models\User;
use App\Services\Opportunities\OpportunityTotalsCalculator;
use App\Services\TaxCalculator;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
    settings()->set('pricing.rounding_direction', PricingRoundingDirection::HalfUp->value);
    settings()->set('tax.unresolved_zone_fallback', 'seller_domestic');
});

/**
 * @return array{member: Member, store: Store, productClass: ProductTaxClass}
 */
function wireMatrixOnlyTax(string $rate = '20.0000'): array
{
    $orgClass = OrganisationTaxClass::factory()->create();
    $productClass = ProductTaxClass::factory()->create(['is_default' => true]);
    $taxRate = TaxRate::factory()->create(['name' => 'Standard', 'rate' => $rate, 'is_active' => true]);
    TaxRule::factory()->create([
        'organisation_tax_class_id' => $orgClass->id,
        'product_tax_class_id' => $productClass->id,
        'tax_rate_id' => $taxRate->id,
        'is_active' => true,
    ]);

    $member = Member::factory()->organisation()->create(['sale_tax_class_id' => $orgClass->id]);
    $store = Store::factory()->create(['country_code' => null, 'tax_zone_id' => null]);

    return [
        'member' => $member,
        'store' => $store,
        'productClass' => $productClass,
        'orgClass' => $orgClass,
    ];
}

/**
 * Legacy opportunity tax_total from pre-P2 groupedTaxTotal semantics.
 *
 * @param  iterable<OpportunityItem>  $items
 * @param  iterable<OpportunityCost>  $costs
 */
function zoneLegacyOpportunityTaxTotal(
    Opportunity $opportunity,
    iterable $items,
    iterable $costs,
    ?int $defaultProductTaxClassId,
): int {
    $calculator = app(TaxCalculator::class);
    $currency = $opportunity->currency_code ?: 'GBP';
    $orgTaxClassId = $opportunity->member?->sale_tax_class_id;

    /** @var array<int, int> $netByProductTaxClass */
    $netByProductTaxClass = [];

    foreach ($items as $item) {
        if ($item->is_optional) {
            continue;
        }

        $product = $item->isProductBacked()
            ? Product::query()->find($item->itemable_id)
            : null;
        $lineNet = (int) $item->total;
        $taxClassKey = $product !== null ? (int) $product->tax_class_id : 0;
        $netByProductTaxClass[$taxClassKey] = ($netByProductTaxClass[$taxClassKey] ?? 0) + $lineNet;
    }

    foreach ($costs as $cost) {
        if ($cost->is_optional) {
            continue;
        }

        $quantityUnits = max(0, (int) round((float) $cost->quantity));
        $lineTotal = $cost->amount * $quantityUnits;

        if ($opportunity->prices_include_tax) {
            $lineTotal = $calculator->calculateInclusive(
                $lineTotal,
                $currency,
                $orgTaxClassId,
                $defaultProductTaxClassId,
            )->netAmount;
        }

        $key = $defaultProductTaxClassId ?? 0;
        $netByProductTaxClass[$key] = ($netByProductTaxClass[$key] ?? 0) + $lineTotal;
    }

    if ($opportunity->deal_total !== null) {
        return $calculator->calculate(
            (int) $opportunity->deal_total,
            $currency,
            $orgTaxClassId,
            $defaultProductTaxClassId,
        )->taxAmount;
    }

    $taxTotal = 0;

    foreach ($netByProductTaxClass as $productTaxClassId => $groupNet) {
        $taxTotal += $calculator->calculate(
            $groupNet,
            $currency,
            $orgTaxClassId,
            $productTaxClassId === 0 ? null : $productTaxClassId,
        )->taxAmount;
    }

    return $taxTotal;
}

describe('zone-aware regression without zone rules', function () {
    it('preserves matrix-only tax_total for a single-class basket', function () {
        ['member' => $member, 'store' => $store, 'productClass' => $productClass] = wireMatrixOnlyTax();
        $product = Product::factory()->sale()->create(['tax_class_id' => $productClass->id]);

        $opportunity = Opportunity::factory()->create([
            'member_id' => $member->id,
            'store_id' => $store->id,
        ]);

        OpportunityItem::factory()->sale()->create([
            'opportunity_id' => $opportunity->id,
            'itemable_id' => $product->id,
            'itemable_type' => Product::class,
            'quantity' => '1',
            'total' => 10_000,
            'unit_price' => 10_000,
        ]);

        $defaultProductTaxClassId = ProductTaxClass::query()->where('is_default', true)->value('id');
        $expectedTax = zoneLegacyOpportunityTaxTotal($opportunity, $opportunity->items, $opportunity->costs, $defaultProductTaxClassId);

        app(OpportunityTotalsCalculator::class)->rollUp($opportunity->refresh());
        $opportunity->refresh();

        expect($opportunity->tax_total)->toBe($expectedTax)
            ->and($opportunity->tax_total)->toBe(2_000);
    });

    it('preserves matrix-only tax_total for mixed-class baskets', function () {
        $orgClass = OrganisationTaxClass::factory()->create();
        $productClass1 = ProductTaxClass::factory()->create();
        $productClass2 = ProductTaxClass::factory()->create();

        TaxRule::factory()->create([
            'organisation_tax_class_id' => $orgClass->id,
            'product_tax_class_id' => $productClass1->id,
            'tax_rate_id' => TaxRate::factory()->create(['rate' => '20.0000', 'is_active' => true])->id,
            'is_active' => true,
        ]);
        TaxRule::factory()->create([
            'organisation_tax_class_id' => $orgClass->id,
            'product_tax_class_id' => $productClass2->id,
            'tax_rate_id' => TaxRate::factory()->create(['rate' => '5.0000', 'is_active' => true])->id,
            'is_active' => true,
        ]);

        $member = Member::factory()->organisation()->create(['sale_tax_class_id' => $orgClass->id]);
        $store = Store::factory()->create(['tax_zone_id' => null, 'country_code' => null]);

        $product1 = Product::factory()->sale()->create(['tax_class_id' => $productClass1->id]);
        $product2 = Product::factory()->sale()->create(['tax_class_id' => $productClass2->id]);

        $opportunity = Opportunity::factory()->create([
            'member_id' => $member->id,
            'store_id' => $store->id,
        ]);

        OpportunityItem::factory()->sale()->create([
            'opportunity_id' => $opportunity->id,
            'itemable_id' => $product1->id,
            'itemable_type' => Product::class,
            'quantity' => '1',
            'total' => 12_347,
            'unit_price' => 12_347,
        ]);
        OpportunityItem::factory()->sale()->create([
            'opportunity_id' => $opportunity->id,
            'itemable_id' => $product2->id,
            'itemable_type' => Product::class,
            'quantity' => '1',
            'total' => 6_789,
            'unit_price' => 6_789,
        ]);

        $defaultProductTaxClassId = ProductTaxClass::query()->where('is_default', true)->value('id');
        $expectedTax = zoneLegacyOpportunityTaxTotal($opportunity, $opportunity->items, $opportunity->costs, $defaultProductTaxClassId);

        app(OpportunityTotalsCalculator::class)->rollUp($opportunity->refresh());

        expect($opportunity->fresh()->tax_total)->toBe($expectedTax)
            ->and($expectedTax)->toBe(2_469 + 339);
    });

    it('preserves matrix-only tax_total for costs', function () {
        ['member' => $member] = wireMatrixOnlyTax();

        $opportunity = Opportunity::factory()->create([
            'member_id' => $member->id,
            'tax_total' => 0,
            'charge_total' => 0,
            'charge_excluding_tax_total' => 0,
            'charge_including_tax_total' => 0,
        ]);

        OpportunityCost::factory()->create([
            'opportunity_id' => $opportunity->id,
            'amount' => 5000,
            'quantity' => '2',
            'is_optional' => false,
        ]);

        $defaultProductTaxClassId = ProductTaxClass::query()->where('is_default', true)->value('id');
        $expectedTax = zoneLegacyOpportunityTaxTotal($opportunity, $opportunity->items, $opportunity->costs, $defaultProductTaxClassId);

        app(OpportunityTotalsCalculator::class)->rollUp($opportunity->refresh());

        expect($opportunity->fresh()->tax_total)->toBe($expectedTax);
    });
});
