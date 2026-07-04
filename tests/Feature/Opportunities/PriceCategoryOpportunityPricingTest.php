<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\CreateOpportunity;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Enums\BasePeriod;
use App\Enums\CalculationStrategyType;
use App\Enums\LineItemTransactionType;
use App\Enums\RateTransactionType;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\PriceCategory;
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
    $this->store = Store::factory()->create(['timezone' => 'UTC']);
});

/**
 * @return array{trade: PriceCategory, product: Product, definition: RateDefinition}
 */
function wireCategoryPricedProduct(int $defaultMinor = 10000, int $tradeMinor = 7500): array
{
    $trade = PriceCategory::factory()->create(['name' => 'Trade']);
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
        'price' => $defaultMinor,
        'price_category_id' => null,
    ]);

    ProductRate::factory()->for($product)->forPriceCategory($trade->id)->create([
        'rate_definition_id' => $definition->id,
        'transaction_type' => RateTransactionType::Rental,
        'price' => $tradeMinor,
    ]);

    return compact('trade', 'product', 'definition');
}

it('prices an opportunity line using the member trade price category rate', function () {
    ['trade' => $trade, 'product' => $product] = wireCategoryPricedProduct(10000, 7500);

    $member = Member::factory()->organisation()->create([
        'price_category_id' => $trade->id,
    ]);

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Trade pricing',
        'member_id' => $member->id,
        'store_id' => $this->store->id,
        'starts_at' => '2026-07-01T09:00:00Z',
        'ends_at' => '2026-07-05T17:00:00Z',
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

    expect($item->unit_price)->toBe(7500)
        ->and($item->total)->toBe(7500);
});

it('prices an opportunity line using the default rate when the member has no category', function () {
    ['product' => $product] = wireCategoryPricedProduct(10000, 7500);

    $member = Member::factory()->organisation()->create([
        'price_category_id' => null,
    ]);

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Default pricing',
        'member_id' => $member->id,
        'store_id' => $this->store->id,
        'starts_at' => '2026-07-01T09:00:00Z',
        'ends_at' => '2026-07-05T17:00:00Z',
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

    expect($item->unit_price)->toBe(10000)
        ->and($item->total)->toBe(10000);
});

it('falls back to the default rate when the member category has no dedicated rate', function () {
    $trade = PriceCategory::factory()->create(['name' => 'Trade']);
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
        'price' => 10000,
        'price_category_id' => null,
    ]);

    $member = Member::factory()->organisation()->create([
        'price_category_id' => $trade->id,
    ]);

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Fallback pricing',
        'member_id' => $member->id,
        'store_id' => $this->store->id,
        'starts_at' => '2026-07-01T09:00:00Z',
        'ends_at' => '2026-07-05T17:00:00Z',
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

    expect($item->unit_price)->toBe(10000)
        ->and($item->total)->toBe(10000);
});
