<?php

use App\Domain\Products\Models\Product;
use App\Domain\Projects\Opportunities\Actions\AddOpportunityItem;
use App\Domain\Projects\Opportunities\Actions\ChangeItemQuantity;
use App\Domain\Projects\Opportunities\Actions\CreateOpportunity;
use App\Domain\Projects\Opportunities\Actions\OverrideOpportunityItemSurcharge;
use App\Domain\Projects\Opportunities\Actions\SetItemDiscount;
use App\Domain\Projects\Opportunities\Actions\ToggleOpportunitySurchargeGroup;
use App\Domain\Projects\Opportunities\Data\AddOpportunityItemData;
use App\Domain\Projects\Opportunities\Data\ChangeItemQuantityData;
use App\Domain\Projects\Opportunities\Data\CreateOpportunityData;
use App\Domain\Projects\Opportunities\Data\OverrideOpportunityItemSurchargeData;
use App\Domain\Projects\Opportunities\Data\SetItemDiscountData;
use App\Domain\Projects\Opportunities\Data\ToggleOpportunitySurchargeGroupData;
use App\Domain\Projects\Opportunities\Models\Opportunity;
use App\Domain\Projects\Opportunities\Models\OpportunityItem;
use App\Domain\Projects\Opportunities\Models\OpportunityItemSurcharge;
use App\Domain\Rates\Enums\BasePeriod;
use App\Domain\Rates\Enums\CalculationStrategyType;
use App\Domain\Rates\Enums\RateTransactionType;
use App\Domain\Rates\Models\ProductRate;
use App\Domain\Rates\Models\RateDefinition;
use App\Domain\Tax\Data\DocumentTaxLineInput;
use App\Domain\Tax\Data\DocumentTaxRequest;
use App\Domain\Tax\Models\OrganisationTaxClass;
use App\Domain\Tax\Models\ProductTaxClass;
use App\Domain\Tax\Models\TaxRate;
use App\Domain\Tax\Models\TaxRule;
use App\Domain\Tax\Services\DocumentTaxBreakdown;
use App\Enums\LineItemTransactionType;
use App\Enums\PricingRoundingDirection;
use App\Models\Member;
use App\Models\Store;
use App\Models\Surcharge;
use App\Models\SurchargeGroup;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
    settings()->set('pricing.rounding_direction', PricingRoundingDirection::HalfUp->value);
    $this->store = Store::factory()->create(['timezone' => 'UTC']);
});

function wireFlatPricedProduct(int $priceMinor = 10_000): Product
{
    $product = Product::factory()->rental()->create();
    $definition = RateDefinition::factory()->create([
        'calculation_strategy' => CalculationStrategyType::Fixed,
        'base_period' => BasePeriod::Daily,
        'enabled_modifiers' => [],
        'strategy_config' => [],
        'modifier_configs' => [],
    ]);

    ProductRate::factory()->for($product)->create([
        'rate_definition_id' => $definition->id,
        'transaction_type' => RateTransactionType::Rental,
        'price' => $priceMinor,
    ]);

    return $product;
}

/**
 * @return array{0: Opportunity, 1: OpportunityItem}
 */
function createPricedOpportunityWithItem(Product $product, ?Member $member = null): array
{
    $member ??= Member::factory()->organisation()->create();

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Surcharge slice',
        'member_id' => $member->id,
        'store_id' => test()->store->id,
        'starts_at' => '2026-07-01T09:00:00Z',
        'ends_at' => '2026-07-02T17:00:00Z',
    ]));
    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => $product->name,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'quantity' => '1',
        'transaction_type' => LineItemTransactionType::Rental->value,
    ]));

    $item = $opportunity->fresh('items')->items->firstOrFail();

    return [$opportunity, $item];
}

it('applies a percent surcharge after discount on the line net', function () {
    $product = wireFlatPricedProduct(10_000);
    $group = SurchargeGroup::factory()->defaultGroup()->create();
    Surcharge::factory()->for($group, 'surchargeGroup')->percent('10.00')->create();

    [$opportunity, $item] = createPricedOpportunityWithItem($product);

    (new SetItemDiscount)($item, SetItemDiscountData::from(['discount_percent' => '10.00']));

    $item->refresh();
    $opportunity->refresh();

    expect($item->total)->toBe(9000)
        ->and($item->surcharge_total)->toBe(900)
        ->and($opportunity->charge_total)->toBe(9900);
});

it('applies a fixed per-unit surcharge multiplied by quantity', function () {
    $product = wireFlatPricedProduct(10_000);
    $group = SurchargeGroup::factory()->defaultGroup()->create();
    Surcharge::factory()->for($group, 'surchargeGroup')->fixed(500)->create();

    [$opportunity, $item] = createPricedOpportunityWithItem($product);

    (new ChangeItemQuantity)(
        $item,
        ChangeItemQuantityData::from(['quantity' => '2']),
    );

    $item->refresh();

    expect($item->surcharge_total)->toBe(1000);
});

it('does not apply surcharges from inactive configured groups', function () {
    $product = wireFlatPricedProduct();
    $group = SurchargeGroup::factory()->defaultGroup()->create();
    Surcharge::factory()->for($group, 'surchargeGroup')->percent('10.00')->create();

    [$opportunity, $item] = createPricedOpportunityWithItem($product);

    (new ToggleOpportunitySurchargeGroup)($opportunity, ToggleOpportunitySurchargeGroupData::from([
        'surcharge_group_id' => $group->id,
        'is_active' => false,
    ]));

    $item->refresh();

    expect($item->surcharge_total)->toBe(0);
});

it('applies surcharges from explicitly enabled non-default groups', function () {
    $product = wireFlatPricedProduct();
    $group = SurchargeGroup::factory()->create(['is_default' => false]);
    Surcharge::factory()->for($group, 'surchargeGroup')->percent('5.00')->create();

    [$opportunity, $item] = createPricedOpportunityWithItem($product);

    expect($item->surcharge_total)->toBe(0);

    (new ToggleOpportunitySurchargeGroup)($opportunity, ToggleOpportunitySurchargeGroupData::from([
        'surcharge_group_id' => $group->id,
        'is_active' => true,
    ]));

    $item->refresh();

    expect($item->surcharge_total)->toBe(500);
});

it('honours a per-line manual surcharge override', function () {
    $product = wireFlatPricedProduct(10_000);
    $group = SurchargeGroup::factory()->defaultGroup()->create();
    $surcharge = Surcharge::factory()->for($group, 'surchargeGroup')->percent('10.00')->create();

    [$opportunity, $item] = createPricedOpportunityWithItem($product);

    (new OverrideOpportunityItemSurcharge)($opportunity, $item, OverrideOpportunityItemSurchargeData::from([
        'surcharge_id' => $surcharge->id,
        'amount_minor' => 1234,
    ]));

    $item->refresh();

    expect($item->surcharge_total)->toBe(1234)
        ->and(OpportunityItemSurcharge::query()->where('opportunity_item_id', $item->id)->first()->is_manual_override)->toBeTrue();
});

it('taxes surcharges at their own product tax class with penny-exact grouped totals', function () {
    $orgClass = OrganisationTaxClass::factory()->create();
    $lineClass = ProductTaxClass::factory()->create(['is_default' => true]);
    $surchargeClass = ProductTaxClass::factory()->create();

    $standardRate = TaxRate::factory()->create(['rate' => '20.0000', 'is_active' => true]);
    $reducedRate = TaxRate::factory()->create(['rate' => '5.0000', 'is_active' => true]);

    TaxRule::factory()->create([
        'organisation_tax_class_id' => $orgClass->id,
        'product_tax_class_id' => $lineClass->id,
        'tax_rate_id' => $standardRate->id,
        'is_active' => true,
    ]);
    TaxRule::factory()->create([
        'organisation_tax_class_id' => $orgClass->id,
        'product_tax_class_id' => $surchargeClass->id,
        'tax_rate_id' => $reducedRate->id,
        'is_active' => true,
    ]);

    $member = Member::factory()->organisation()->create([
        'sale_tax_class_id' => $orgClass->id,
    ]);

    $product = Product::factory()->rental()->create([
        'tax_class_id' => $lineClass->id,
    ]);

    $definition = RateDefinition::factory()->create([
        'calculation_strategy' => CalculationStrategyType::Fixed,
        'base_period' => BasePeriod::Daily,
        'enabled_modifiers' => [],
        'strategy_config' => [],
        'modifier_configs' => [],
    ]);

    ProductRate::factory()->for($product)->create([
        'rate_definition_id' => $definition->id,
        'transaction_type' => RateTransactionType::Rental,
        'price' => 12_347,
    ]);

    $group = SurchargeGroup::factory()->defaultGroup()->create();
    Surcharge::factory()->for($group, 'surchargeGroup')->fixed(678)->create([
        'product_tax_class_id' => $surchargeClass->id,
    ]);

    [$opportunity, $item] = createPricedOpportunityWithItem($product, $member);

    $expected = app(DocumentTaxBreakdown::class)->summarize(new DocumentTaxRequest(
        lines: [
            new DocumentTaxLineInput(lineKey: 'line', netMinor: $item->total, productTaxClassId: $lineClass->id),
            new DocumentTaxLineInput(lineKey: 'surcharge', netMinor: $item->surcharge_total, productTaxClassId: $surchargeClass->id),
        ],
        currencyCode: 'GBP',
        organisationTaxClassId: $orgClass->id,
    ));

    $opportunity->refresh();

    expect($item->surcharge_total)->toBe(678)
        ->and($opportunity->tax_total)->toBe($expected->totalTax)
        ->and($expected->totalTax)->toBe(2469 + 34);
});
