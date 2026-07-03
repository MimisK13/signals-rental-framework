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
use App\Models\BundleRule;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ProductRate;
use App\Models\RateDefinition;
use App\Models\Store;
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

function bundleFlatProduct(int $priceMinor = 10_000): Product
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

function createBundleOpportunity(array $lines): Opportunity
{
    $member = Member::factory()->organisation()->create();

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Bundle slice',
        'member_id' => $member->id,
        'store_id' => test()->store->id,
        'starts_at' => '2026-07-01T09:00:00Z',
        'ends_at' => '2026-07-02T17:00:00Z',
    ]));
    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();

    foreach ($lines as $line) {
        (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
            'name' => $line['product']->name,
            'itemable_id' => $line['product']->id,
            'itemable_type' => Product::class,
            'quantity' => (string) ($line['quantity'] ?? 1),
            'transaction_type' => LineItemTransactionType::Rental->value,
            'revenue_group_id' => $line['revenue_group_id'] ?? 7,
        ]));
        $opportunity = $opportunity->fresh('items');
    }

    return $opportunity->fresh('items');
}

it('applies a fixed bundle price and re-attributes line nets penny-exactly', function () {
    $productA = bundleFlatProduct(10_000);
    $productB = bundleFlatProduct(10_000);

    BundleRule::factory()->forProducts([$productA->id, $productB->id])->fixed(15_000)->create();

    $opportunity = createBundleOpportunity([
        ['product' => $productA],
        ['product' => $productB],
    ]);

    $items = $opportunity->items->sortBy('id')->values();

    expect($items)->toHaveCount(2)
        ->and($items->sum('total'))->toBe(15_000)
        ->and($items[0]->total + $items[1]->total)->toBe(15_000);
});

it('applies a percent bundle reduction before line discounts', function () {
    $productA = bundleFlatProduct(10_000);
    $productB = bundleFlatProduct(10_000);

    BundleRule::factory()->forProducts([$productA->id, $productB->id])->percent('10.00')->create();

    $opportunity = createBundleOpportunity([
        ['product' => $productA],
        ['product' => $productB],
    ]);

    expect($opportunity->items->sum('total'))->toBe(18_000);
});

it('does not bundle lines in different revenue groups', function () {
    $productA = bundleFlatProduct(10_000);
    $productB = bundleFlatProduct(10_000);

    BundleRule::factory()->forProducts([$productA->id, $productB->id])->fixed(12_000)->create();

    $opportunity = createBundleOpportunity([
        ['product' => $productA, 'revenue_group_id' => 1],
        ['product' => $productB, 'revenue_group_id' => 2],
    ]);

    expect($opportunity->items->sum('total'))->toBe(20_000);
});

it('distributes uneven bundle fixed prices with largest-remainder exactness', function () {
    $productA = bundleFlatProduct(10_000);
    $productB = bundleFlatProduct(20_000);
    $productC = bundleFlatProduct(30_000);

    BundleRule::factory()->forProducts([$productA->id, $productB->id, $productC->id])->fixed(10_001)->create();

    $opportunity = createBundleOpportunity([
        ['product' => $productA],
        ['product' => $productB],
        ['product' => $productC],
    ]);

    expect($opportunity->items->sum('total'))->toBe(10_001);
});
