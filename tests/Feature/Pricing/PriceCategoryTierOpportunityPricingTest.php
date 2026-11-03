<?php

use App\Domain\Members\Actions\UpdateMember;
use App\Domain\Members\Data\UpdateMemberData;
use App\Domain\Members\Models\Member;
use App\Domain\Members\Presentation\Livewire\Form;
use App\Domain\Pricing\Enums\PricingRoundingDirection;
use App\Domain\Pricing\Models\PriceCategory;
use App\Domain\Pricing\Models\PriceCategoryTier;
use App\Domain\Pricing\Services\OpportunityPricingRecalculator;
use App\Domain\Pricing\Services\PriceCategoryTierMultiplier;
use App\Domain\Products\Models\Product;
use App\Domain\Projects\Opportunities\Actions\AddOpportunityItem;
use App\Domain\Projects\Opportunities\Actions\CreateOpportunity;
use App\Domain\Projects\Opportunities\Actions\SetItemDiscount;
use App\Domain\Projects\Opportunities\Data\AddOpportunityItemData;
use App\Domain\Projects\Opportunities\Data\CreateOpportunityData;
use App\Domain\Projects\Opportunities\Data\SetItemDiscountData;
use App\Domain\Projects\Opportunities\Models\Opportunity;
use App\Domain\Rates\Enums\BasePeriod;
use App\Domain\Rates\Enums\CalculationStrategyType;
use App\Domain\Rates\Enums\RateTransactionType;
use App\Domain\Rates\Models\ProductRate;
use App\Domain\Rates\Models\RateDefinition;
use App\Domain\Stores\Models\Store;
use App\Enums\LineItemTransactionType;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Thunk\Verbs\Facades\Verbs;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
    settings()->set('pricing.rounding_direction', PricingRoundingDirection::HalfUp->value);
    $this->store = Store::factory()->create(['timezone' => 'UTC']);
});

/**
 * @return array{trade: PriceCategory, tier: PriceCategoryTier, product: Product}
 */
function tierWireCategoryPricedProduct(
    int $defaultMinor = 10_000,
    int $tradeMinor = 7_500,
    string $multiplier = '0.9500',
): array {
    $trade = PriceCategory::factory()->create(['name' => 'Trade']);
    $tier = PriceCategoryTier::factory()->for($trade)->create([
        'tier_number' => 2,
        'label' => 'Trade tier 2',
        'multiplier' => $multiplier,
        'sort_order' => 1,
    ]);
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

    return compact('trade', 'tier', 'product');
}

function tierAddPricedLine(Member $member, Product $product): Opportunity
{
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Tier pricing',
        'member_id' => $member->id,
        'store_id' => test()->store->id,
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

    return $opportunity->fresh('items');
}

it('applies the tier multiplier to a category-specific rate in stage 1', function () {
    ['trade' => $trade, 'tier' => $tier, 'product' => $product] = tierWireCategoryPricedProduct(10_000, 7_500, '0.9500');

    $member = Member::factory()->organisation()->create([
        'price_category_id' => $trade->id,
        'price_category_tier_id' => $tier->id,
    ]);

    $opportunity = tierAddPricedLine($member, $product);
    $item = $opportunity->items->firstOrFail();

    expect($item->unit_price)->toBe(7_125)
        ->and($item->total)->toBe(7_125)
        ->and($item->price_category_tier_id)->toBe($tier->id)
        ->and((string) $item->price_category_tier_multiplier)->toBe('0.9500');
});

it('rounds tier-adjusted unit prices using the tenant rounding direction', function () {
    settings()->set('pricing.rounding_direction', PricingRoundingDirection::HalfUp->value);
    $halfUp = app(PriceCategoryTierMultiplier::class)->apply(
        5_263,
        'GBP',
        1,
        new PriceCategoryTier(['id' => 1, 'multiplier' => '0.9500']),
        1,
    )['unit_price'];

    settings()->set('pricing.rounding_direction', PricingRoundingDirection::Bankers->value);
    $bankers = app(PriceCategoryTierMultiplier::class)->apply(
        5_263,
        'GBP',
        1,
        new PriceCategoryTier(['id' => 1, 'multiplier' => '0.9500']),
        1,
    )['unit_price'];

    expect($halfUp)->toBe(5_000)
        ->and($bankers)->toBe(5_000);

    settings()->set('pricing.rounding_direction', PricingRoundingDirection::HalfUp->value);
    expect(app(PriceCategoryTierMultiplier::class)->apply(
        10_001,
        'GBP',
        1,
        new PriceCategoryTier(['id' => 1, 'multiplier' => '0.9500']),
        1,
    )['unit_price'])->toBe(9_501);
});

it('does not multiply fallback default rates when the category has no dedicated rate', function () {
    $trade = PriceCategory::factory()->create(['name' => 'Trade']);
    $tier = PriceCategoryTier::factory()->for($trade)->create(['multiplier' => '0.9500']);
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
        'price' => 10_000,
        'price_category_id' => null,
    ]);

    $member = Member::factory()->organisation()->create([
        'price_category_id' => $trade->id,
        'price_category_tier_id' => $tier->id,
    ]);

    $opportunity = tierAddPricedLine($member, $product);
    $item = $opportunity->items->firstOrFail();

    expect($item->unit_price)->toBe(10_000)
        ->and($item->price_category_tier_id)->toBeNull()
        ->and($item->price_category_tier_multiplier)->toBeNull();
});

it('leaves category pricing unchanged when the member has no tier', function () {
    ['trade' => $trade, 'product' => $product] = tierWireCategoryPricedProduct();

    $member = Member::factory()->organisation()->create([
        'price_category_id' => $trade->id,
        'price_category_tier_id' => null,
    ]);

    $opportunity = tierAddPricedLine($member, $product);
    $item = $opportunity->items->firstOrFail();

    expect($item->unit_price)->toBe(7_500)
        ->and($item->price_category_tier_id)->toBeNull()
        ->and($item->price_category_tier_multiplier)->toBeNull();
});

it('applies line discounts after the tier-adjusted stage 1 price', function () {
    ['trade' => $trade, 'tier' => $tier, 'product' => $product] = tierWireCategoryPricedProduct(10_000, 10_000, '0.9500');

    $member = Member::factory()->organisation()->create([
        'price_category_id' => $trade->id,
        'price_category_tier_id' => $tier->id,
    ]);

    $opportunity = tierAddPricedLine($member, $product);
    $item = $opportunity->items->firstOrFail();

    expect($item->unit_price)->toBe(9_500);

    (new SetItemDiscount)($item, SetItemDiscountData::from([
        'discount_percent' => '10.00',
        'is_discount_manual' => true,
    ]));

    $item->refresh();

    expect($item->unit_price)->toBe(9_500)
        ->and($item->total)->toBe(8_550);
});

it('records tier snapshots and reproduces them on replay', function () {
    ['trade' => $trade, 'tier' => $tier, 'product' => $product] = tierWireCategoryPricedProduct();

    $member = Member::factory()->organisation()->create([
        'price_category_id' => $trade->id,
        'price_category_tier_id' => $tier->id,
    ]);

    $opportunity = tierAddPricedLine($member, $product);
    $item = $opportunity->items->firstOrFail();

    $expectedUnitPrice = $item->unit_price;
    $expectedTierId = $item->price_category_tier_id;
    $expectedMultiplier = (string) $item->price_category_tier_multiplier;

    $eventCountBeforeReplay = verbEventCount();

    Verbs::replay();

    $item->refresh();

    expect(verbEventCount())->toBe($eventCountBeforeReplay)
        ->and($item->unit_price)->toBe($expectedUnitPrice)
        ->and($item->price_category_tier_id)->toBe($expectedTierId)
        ->and((string) $item->price_category_tier_multiplier)->toBe($expectedMultiplier);
});

it('re-reads the member current tier when pricing is recalculated', function () {
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
        'price' => 10_000,
        'price_category_id' => null,
    ]);

    ProductRate::factory()->for($product)->forPriceCategory($trade->id)->create([
        'rate_definition_id' => $definition->id,
        'transaction_type' => RateTransactionType::Rental,
        'price' => 10_000,
    ]);

    $tierBase = PriceCategoryTier::factory()->for($trade)->create([
        'tier_number' => 1,
        'label' => 'Base',
        'multiplier' => '1.0000',
        'sort_order' => 0,
    ]);
    $tierDiscount = PriceCategoryTier::factory()->for($trade)->create([
        'tier_number' => 2,
        'label' => 'Discount',
        'multiplier' => '0.9000',
        'sort_order' => 1,
    ]);

    $member = Member::factory()->organisation()->create([
        'price_category_id' => $trade->id,
        'price_category_tier_id' => $tierBase->id,
    ]);

    $opportunity = tierAddPricedLine($member, $product);
    $item = $opportunity->items->firstOrFail();

    expect($item->unit_price)->toBe(10_000)
        ->and($item->price_category_tier_id)->toBe($tierBase->id);

    $member->update(['price_category_tier_id' => $tierDiscount->id]);

    app(OpportunityPricingRecalculator::class)->recalculate($opportunity->fresh());

    $item->refresh();

    expect($item->unit_price)->toBe(9_000)
        ->and($item->price_category_tier_id)->toBe($tierDiscount->id)
        ->and((string) $item->price_category_tier_multiplier)->toBe('0.9000');
});

it('rejects a tier that does not belong to the submitted price category', function () {
    $trade = PriceCategory::factory()->create(['name' => 'Trade']);
    $retail = PriceCategory::factory()->create(['name' => 'Retail']);
    $retailTier = PriceCategoryTier::factory()->for($retail)->create(['multiplier' => '0.9500']);
    $member = Member::factory()->organisation()->create(['price_category_id' => $trade->id]);

    expect(fn () => (new UpdateMember)($member, UpdateMemberData::from([
        'price_category_id' => $trade->id,
        'price_category_tier_id' => $retailTier->id,
    ])))->toThrow(ValidationException::class);
});

it('filters members by price_category_tier_id via the API', function () {
    $trade = PriceCategory::factory()->create(['name' => 'Trade']);
    $tierOne = PriceCategoryTier::factory()->for($trade)->create(['tier_number' => 1, 'multiplier' => '1.0000']);
    $tierTwo = PriceCategoryTier::factory()->for($trade)->create(['tier_number' => 2, 'multiplier' => '0.9500']);

    $matched = Member::factory()->organisation()->create([
        'name' => 'Tier Two Org',
        'price_category_id' => $trade->id,
        'price_category_tier_id' => $tierTwo->id,
    ]);
    Member::factory()->organisation()->create([
        'name' => 'Tier One Org',
        'price_category_id' => $trade->id,
        'price_category_tier_id' => $tierOne->id,
    ]);

    $token = User::factory()->owner()->create()->createToken('test', ['members:read'])->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/members?q[price_category_tier_id_eq]='.$tierTwo->id)
        ->assertSuccessful()
        ->assertJsonCount(1, 'members')
        ->assertJsonPath('members.0.id', $matched->id)
        ->assertJsonPath('members.0.membership.price_category_tier_id', $tierTwo->id);
});

it('clears the selected tier when the price category changes in the member form', function () {
    $trade = PriceCategory::factory()->create(['name' => 'Trade']);
    $retail = PriceCategory::factory()->create(['name' => 'Retail']);
    $tradeTier = PriceCategoryTier::factory()->for($trade)->create(['label' => 'Trade tier', 'multiplier' => '0.9500']);
    $member = Member::factory()->organisation()->create([
        'price_category_id' => $trade->id,
        'price_category_tier_id' => $tradeTier->id,
    ]);

    Livewire::test(Form::class, ['member' => $member])
        ->assertSet('priceCategoryTierId', $tradeTier->id)
        ->set('priceCategoryId', $retail->id)
        ->assertSet('priceCategoryTierId', null);
});
