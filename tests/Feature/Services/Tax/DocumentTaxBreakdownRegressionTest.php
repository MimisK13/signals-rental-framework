<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\CreateOpportunity;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Enums\BasePeriod;
use App\Enums\CalculationStrategyType;
use App\Enums\LineItemTransactionType;
use App\Enums\PricingRoundingDirection;
use App\Enums\RateTransactionType;
use App\Models\Currency;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\OpportunityCost;
use App\Models\OpportunityItem;
use App\Models\OrganisationTaxClass;
use App\Models\Product;
use App\Models\ProductRate;
use App\Models\ProductTaxClass;
use App\Models\RateDefinition;
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
});

function rollupCalculator(): OpportunityTotalsCalculator
{
    return app(OpportunityTotalsCalculator::class);
}

/**
 * @return array{member: Member, store: Store, productClass: ProductTaxClass}
 */
function wireSingleClassTax(string $rate = '20.0000'): array
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
    $store = Store::factory()->create();

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
 */
function legacyOpportunityTaxTotal(
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

describe('opportunity rollUp #238 proof', function () {
    it('preserves tax_total and per-type totals for a single-class basket', function () {
        ['member' => $member, 'store' => $store, 'productClass' => $productClass] = wireSingleClassTax();
        $product = Product::factory()->rental()->create(['tax_class_id' => $productClass->id]);

        $created = (new CreateOpportunity)(CreateOpportunityData::from([
            'subject' => 'Single class',
            'member_id' => $member->id,
            'store_id' => $store->id,
            'starts_at' => '2026-07-01T09:00:00Z',
            'ends_at' => '2026-07-02T09:00:00Z',
        ]));
        $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();

        (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
            'name' => $product->name,
            'itemable_id' => $product->id,
            'itemable_type' => Product::class,
            'quantity' => '2',
            'unit_price' => 5000,
            'transaction_type' => LineItemTransactionType::Sale->value,
        ]));

        $opportunity->refresh();
        $expectedTax = legacyOpportunityTaxTotal(
            $opportunity,
            $opportunity->items,
            $opportunity->costs,
            ProductTaxClass::query()->where('is_default', true)->value('id'),
        );

        rollupCalculator()->rollUp($opportunity->refresh());
        $opportunity->refresh();

        expect($opportunity->tax_total)->toBe($expectedTax)
            ->and($opportunity->sale_charge_total)->toBe(10_000)
            ->and($opportunity->charge_excluding_tax_total)->toBe(10_000)
            ->and($opportunity->charge_including_tax_total)->toBe(10_000 + $expectedTax);
    });

    it('preserves tax_total for the mixed-class basket regression matrix', function () {
        $store = Store::factory()->create();
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

        $definition = RateDefinition::factory()->create([
            'calculation_strategy' => CalculationStrategyType::Period,
            'base_period' => BasePeriod::Daily,
            'enabled_modifiers' => [],
            'strategy_config' => [],
            'modifier_configs' => [],
        ]);

        $product1 = Product::factory()->rental()->create(['tax_class_id' => $productClass1->id]);
        $product2 = Product::factory()->rental()->create(['tax_class_id' => $productClass2->id]);

        ProductRate::factory()->create([
            'product_id' => $product1->id,
            'rate_definition_id' => $definition->id,
            'transaction_type' => RateTransactionType::Rental,
            'price' => 12_347,
            'currency' => 'GBP',
        ]);
        ProductRate::factory()->create([
            'product_id' => $product2->id,
            'rate_definition_id' => $definition->id,
            'transaction_type' => RateTransactionType::Rental,
            'price' => 6_789,
            'currency' => 'GBP',
        ]);

        $created = (new CreateOpportunity)(CreateOpportunityData::from([
            'subject' => 'Mixed tax basket',
            'member_id' => $member->id,
            'store_id' => $store->id,
            'starts_at' => '2026-07-01T09:00:00Z',
            'ends_at' => '2026-07-02T09:00:00Z',
        ]));
        $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();

        foreach ([$product1, $product2] as $product) {
            (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
                'name' => $product->name,
                'itemable_id' => $product->id,
                'itemable_type' => Product::class,
                'quantity' => '1',
                'transaction_type' => LineItemTransactionType::Rental->value,
            ]));
        }

        $opportunity->refresh();
        $defaultProductTaxClassId = ProductTaxClass::query()->where('is_default', true)->value('id');
        $expectedTax = legacyOpportunityTaxTotal($opportunity, $opportunity->items, $opportunity->costs, $defaultProductTaxClassId);

        rollupCalculator()->rollUp($opportunity->refresh());
        $opportunity->refresh();

        expect($opportunity->tax_total)->toBe($expectedTax)
            ->and($expectedTax)->toBe(2_808)
            ->and($opportunity->rental_charge_total)->toBe(19_136)
            ->and($opportunity->charge_excluding_tax_total)->toBe(19_136)
            ->and($opportunity->charge_including_tax_total)->toBe(19_136 + $expectedTax);
    });

    it('preserves tax_total for ad-hoc lines without a product class', function () {
        ['member' => $member, 'store' => $store, 'productClass' => $productClass] = wireSingleClassTax();
        $product = Product::factory()->rental()->create(['tax_class_id' => $productClass->id]);

        $opportunity = Opportunity::factory()->create([
            'member_id' => $member->id,
            'store_id' => $store->id,
            'starts_at' => '2026-07-01T09:00:00Z',
            'ends_at' => '2026-07-02T09:00:00Z',
        ]);

        OpportunityItem::factory()->sale()->create([
            'opportunity_id' => $opportunity->id,
            'itemable_id' => null,
            'itemable_type' => null,
            'quantity' => '1',
            'total' => 5_000,
            'unit_price' => 5_000,
        ]);
        OpportunityItem::factory()->sale()->create([
            'opportunity_id' => $opportunity->id,
            'itemable_id' => $product->id,
            'itemable_type' => Product::class,
            'quantity' => '1',
            'total' => 1_000,
            'unit_price' => 1_000,
        ]);

        $defaultProductTaxClassId = ProductTaxClass::query()->where('is_default', true)->value('id');
        $expectedTax = legacyOpportunityTaxTotal($opportunity, $opportunity->items, $opportunity->costs, $defaultProductTaxClassId);

        rollupCalculator()->rollUp($opportunity->refresh());
        $opportunity->refresh();

        expect($opportunity->tax_total)->toBe($expectedTax)
            ->and($opportunity->tax_total)->toBe(200);
    });

    it('preserves tax_total for costs using the default product class fallback', function () {
        ['member' => $member] = wireSingleClassTax();

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
        $expectedTax = legacyOpportunityTaxTotal($opportunity, $opportunity->items, $opportunity->costs, $defaultProductTaxClassId);

        rollupCalculator()->rollUp($opportunity->refresh());
        $opportunity->refresh();

        expect($opportunity->tax_total)->toBe($expectedTax)
            ->and($opportunity->service_charge_total)->toBe(10_000)
            ->and($opportunity->charge_total)->toBe(10_000);
    });

    it('preserves tax_total for prices-include-tax opportunities', function () {
        ['member' => $member, 'productClass' => $productClass] = wireSingleClassTax();
        $product = Product::factory()->create(['tax_class_id' => $productClass->id]);

        $opportunity = Opportunity::factory()->create([
            'member_id' => $member->id,
            'prices_include_tax' => true,
            'starts_at' => '2026-07-01T09:00:00Z',
            'ends_at' => '2026-07-02T09:00:00Z',
            'tax_total' => 0,
            'charge_total' => 0,
            'charge_excluding_tax_total' => 0,
            'charge_including_tax_total' => 0,
        ]);

        $item = OpportunityItem::factory()->sale()->create([
            'opportunity_id' => $opportunity->id,
            'itemable_id' => $product->id,
            'itemable_type' => Product::class,
            'quantity' => '1',
            'unit_price' => 12_000,
            'total' => 12_000,
            'starts_at' => $opportunity->starts_at,
            'ends_at' => $opportunity->ends_at,
        ]);

        rollupCalculator()->recalculateItem($item->refresh(), manualUnitPrice: 12_000);
        $defaultProductTaxClassId = ProductTaxClass::query()->where('is_default', true)->value('id');
        $expectedTax = legacyOpportunityTaxTotal($opportunity, $opportunity->items()->get(), $opportunity->costs, $defaultProductTaxClassId);

        rollupCalculator()->rollUp($opportunity->refresh());
        $opportunity->refresh();

        expect($opportunity->tax_total)->toBe($expectedTax)
            ->and($opportunity->charge_total)->toBe(10_000)
            ->and($opportunity->charge_including_tax_total)->toBe(12_000);
    });

    it('preserves tax_total for JPY and KWD currency scales', function () {
        Currency::factory()->create(['code' => 'JPY', 'decimal_places' => 0, 'symbol' => '¥']);
        Currency::factory()->create(['code' => 'KWD', 'decimal_places' => 3, 'symbol' => 'KD']);

        foreach (['JPY' => 1_005, 'KWD' => 1_2345] as $currencyCode => $netMinor) {
            ['member' => $member, 'productClass' => $productClass] = wireSingleClassTax();
            $product = Product::factory()->sale()->create(['tax_class_id' => $productClass->id]);

            $opportunity = Opportunity::factory()->create([
                'member_id' => $member->id,
                'currency_code' => $currencyCode,
                'tax_total' => 0,
                'charge_total' => 0,
                'charge_excluding_tax_total' => 0,
                'charge_including_tax_total' => 0,
            ]);

            OpportunityItem::factory()->sale()->create([
                'opportunity_id' => $opportunity->id,
                'itemable_id' => $product->id,
                'itemable_type' => Product::class,
                'quantity' => '1',
                'total' => $netMinor,
                'unit_price' => $netMinor,
            ]);

            $defaultProductTaxClassId = ProductTaxClass::query()->where('is_default', true)->value('id');
            $expectedTax = legacyOpportunityTaxTotal($opportunity, $opportunity->items, $opportunity->costs, $defaultProductTaxClassId);

            rollupCalculator()->rollUp($opportunity->refresh());

            expect($opportunity->fresh()->tax_total)->toBe($expectedTax);
        }
    });
});
