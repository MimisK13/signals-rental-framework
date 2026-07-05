<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\SetItemDiscount;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\SetItemDiscountData;
use App\Data\Tax\DocumentTaxLineInput;
use App\Data\Tax\DocumentTaxRequest;
use App\Enums\BasePeriod;
use App\Enums\CalculationStrategyType;
use App\Enums\LineItemTransactionType;
use App\Enums\PricingRoundingDirection;
use App\Enums\RateTransactionType;
use App\Models\BundleRule;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\OrganisationTaxClass;
use App\Models\Product;
use App\Models\ProductRate;
use App\Models\ProductTaxClass;
use App\Models\RateDefinition;
use App\Models\Store;
use App\Models\Surcharge;
use App\Models\SurchargeGroup;
use App\Models\TaxRate;
use App\Models\TaxRule;
use App\Models\User;
use App\Services\Tax\DocumentTaxBreakdown;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
    settings()->set('pricing.rounding_direction', PricingRoundingDirection::HalfUp->value);
    $this->store = Store::factory()->create(['timezone' => 'UTC']);
});

it('orders bundle then discount then surcharge before grouped tax', function () {
    $orgClass = OrganisationTaxClass::factory()->create();
    $lineClass = ProductTaxClass::factory()->create(['is_default' => true]);
    $standardRate = TaxRate::factory()->create(['rate' => '20.0000', 'is_active' => true]);

    TaxRule::factory()->create([
        'organisation_tax_class_id' => $orgClass->id,
        'product_tax_class_id' => $lineClass->id,
        'tax_rate_id' => $standardRate->id,
        'is_active' => true,
    ]);

    $member = Member::factory()->organisation()->create(['sale_tax_class_id' => $orgClass->id]);

    $productA = Product::factory()->rental()->create(['tax_class_id' => $lineClass->id]);
    $productB = Product::factory()->rental()->create(['tax_class_id' => $lineClass->id]);

    $definition = RateDefinition::factory()->create([
        'calculation_strategy' => CalculationStrategyType::Fixed,
        'base_period' => BasePeriod::Daily,
        'enabled_modifiers' => [],
        'strategy_config' => [],
        'modifier_configs' => [],
    ]);

    foreach ([$productA, $productB] as $product) {
        ProductRate::factory()->for($product)->create([
            'rate_definition_id' => $definition->id,
            'transaction_type' => RateTransactionType::Rental,
            'price' => 10_000,
        ]);
    }

    BundleRule::factory()->forProducts([$productA->id, $productB->id])->fixed(18_000)->create();

    $group = SurchargeGroup::factory()->defaultGroup()->create();
    Surcharge::factory()->for($group, 'surchargeGroup')->percent('10.00')->create();

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Pipeline ordering',
        'member_id' => $member->id,
        'store_id' => $this->store->id,
        'starts_at' => '2026-07-01T09:00:00Z',
        'ends_at' => '2026-07-02T17:00:00Z',
    ]));
    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();

    foreach ([$productA, $productB] as $product) {
        (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
            'name' => $product->name,
            'itemable_id' => $product->id,
            'itemable_type' => Product::class,
            'quantity' => '1',
            'transaction_type' => LineItemTransactionType::Rental->value,
            'revenue_group_id' => 7,
        ]));
        $opportunity = $opportunity->fresh('items');
    }

    $items = $opportunity->items->sortBy('id')->values();

    expect($items->sum('total'))->toBe(18_000);

    foreach ($items as $item) {
        (new SetItemDiscount)($item, SetItemDiscountData::from(['discount_percent' => '10.00']));
    }

    $items = $opportunity->fresh('items')->items->sortBy('id')->values();

    expect($items->sum('total'))->toBe(16_200)
        ->and($items->sum('surcharge_total'))->toBe(1620);

    $expectedTax = app(DocumentTaxBreakdown::class)->summarize(new DocumentTaxRequest(
        lines: $items->flatMap(fn ($item) => [
            new DocumentTaxLineInput(lineKey: 'line:'.$item->id, netMinor: $item->total, productTaxClassId: $lineClass->id),
            new DocumentTaxLineInput(lineKey: 'surcharge:'.$item->id, netMinor: $item->surcharge_total, productTaxClassId: $lineClass->id),
        ])->all(),
        currencyCode: 'GBP',
        organisationTaxClassId: $orgClass->id,
    ))->totalTax;

    $opportunity->refresh();

    expect($opportunity->charge_total)->toBe(17_820)
        ->and($opportunity->tax_total)->toBe($expectedTax);
});
