<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\ChangeItemQuantity;
use App\Actions\Opportunities\CreateOpportunity;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\ChangeItemQuantityData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Enums\DiscountRuleScope;
use App\Enums\LineItemTransactionType;
use App\Models\DiscountCategory;
use App\Models\DiscountRule;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Product;
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
 * @return array{0: Opportunity, 1: OpportunityItem}
 */
function createDiscountedOpportunity(Member $member, Product $product, ?int $revenueGroupId = null): array
{
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Quantity break slice',
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
        'quantity' => '9',
        'transaction_type' => LineItemTransactionType::Rental->value,
        'revenue_group_id' => $revenueGroupId,
        'unit_price' => 1000,
    ]));

    $item = $opportunity->fresh('items')->items->firstOrFail();

    return [$opportunity, $item];
}

it('auto-applies a per_line quantity break when quantity reaches the threshold', function () {
    $category = DiscountCategory::factory()->create();
    DiscountRule::factory()->for($category)->create([
        'discount_percent' => '15.00',
        'criteria' => ['quantity_min' => 10],
        'rule_scope' => DiscountRuleScope::PerLine,
    ]);

    $member = Member::factory()->organisation()->create(['discount_category_id' => $category->id]);
    $product = Product::factory()->rental()->create();

    [$opportunity, $item] = createDiscountedOpportunity($member, $product);

    expect($item->discount_percent)->toBeNull();

    (new ChangeItemQuantity)($item, ChangeItemQuantityData::from(['quantity' => '10']));

    $item->refresh();

    expect($item->discount_percent)->toBe('15.00');
});

it('clears an automatic quantity break when quantity drops below the threshold', function () {
    $category = DiscountCategory::factory()->create();
    DiscountRule::factory()->for($category)->create([
        'discount_percent' => '15.00',
        'criteria' => ['quantity_min' => 10],
        'rule_scope' => DiscountRuleScope::PerLine,
    ]);

    $member = Member::factory()->organisation()->create(['discount_category_id' => $category->id]);
    $product = Product::factory()->rental()->create();

    [$opportunity, $item] = createDiscountedOpportunity($member, $product);

    (new ChangeItemQuantity)($item, ChangeItemQuantityData::from(['quantity' => '10']));
    $item->refresh();

    expect($item->discount_percent)->toBe('15.00');

    (new ChangeItemQuantity)($item, ChangeItemQuantityData::from(['quantity' => '9']));
    $item->refresh();

    expect($item->discount_percent)->toBeNull();
});

it('applies aggregate_product volume discounts when summed quantity qualifies', function () {
    $category = DiscountCategory::factory()->create();
    DiscountRule::factory()->for($category)->create([
        'discount_percent' => '20.00',
        'criteria' => ['quantity_min' => 10],
        'rule_scope' => DiscountRuleScope::AggregateProduct,
    ]);

    $member = Member::factory()->organisation()->create(['discount_category_id' => $category->id]);
    $product = Product::factory()->rental()->create();

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Aggregate product',
        'member_id' => $member->id,
        'store_id' => $this->store->id,
        'starts_at' => '2026-07-01T09:00:00Z',
        'ends_at' => '2026-07-02T17:00:00Z',
    ]));
    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => $product->name,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'quantity' => '4',
        'unit_price' => 1000,
    ]));

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => $product->name,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'quantity' => '6',
        'unit_price' => 1000,
    ]));

    $items = $opportunity->fresh('items')->items;

    expect($items->every(fn ($item) => $item->discount_percent === '20.00'))->toBeTrue();
});

it('applies aggregate_group volume discounts within the same revenue group only', function () {
    $category = DiscountCategory::factory()->create();
    DiscountRule::factory()->for($category)->create([
        'discount_percent' => '12.00',
        'criteria' => ['quantity_min' => 8],
        'rule_scope' => DiscountRuleScope::AggregateGroup,
    ]);

    $member = Member::factory()->organisation()->create(['discount_category_id' => $category->id]);
    $productA = Product::factory()->rental()->create();
    $productB = Product::factory()->rental()->create();

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Aggregate group',
        'member_id' => $member->id,
        'store_id' => $this->store->id,
        'starts_at' => '2026-07-01T09:00:00Z',
        'ends_at' => '2026-07-02T17:00:00Z',
    ]));
    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => $productA->name,
        'itemable_id' => $productA->id,
        'itemable_type' => Product::class,
        'quantity' => '3',
        'revenue_group_id' => 55,
        'unit_price' => 1000,
    ]));

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => $productB->name,
        'itemable_id' => $productB->id,
        'itemable_type' => Product::class,
        'quantity' => '5',
        'revenue_group_id' => 55,
        'unit_price' => 1000,
    ]));

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => $productA->name,
        'itemable_id' => $productA->id,
        'itemable_type' => Product::class,
        'quantity' => '3',
        'revenue_group_id' => 99,
        'unit_price' => 1000,
    ]));

    $items = $opportunity->fresh('items')->items;
    $inGroup = $items->where('revenue_group_id', 55);
    $outside = $items->firstWhere('revenue_group_id', 99);

    expect($inGroup)->toHaveCount(2)
        ->and($inGroup->every(fn ($item) => $item->discount_percent === '12.00'))->toBeTrue()
        ->and($outside?->discount_percent)->toBeNull();
});
