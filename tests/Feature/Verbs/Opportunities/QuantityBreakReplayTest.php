<?php

use App\Domain\Members\Models\Member;
use App\Domain\Pricing\Enums\DiscountRuleScope;
use App\Domain\Pricing\Models\DiscountCategory;
use App\Domain\Pricing\Models\DiscountRule;
use App\Domain\Products\Models\Product;
use App\Domain\Projects\Opportunities\Actions\AddOpportunityItem;
use App\Domain\Projects\Opportunities\Actions\ChangeItemQuantity;
use App\Domain\Projects\Opportunities\Actions\CreateOpportunity;
use App\Domain\Projects\Opportunities\Data\AddOpportunityItemData;
use App\Domain\Projects\Opportunities\Data\ChangeItemQuantityData;
use App\Domain\Projects\Opportunities\Data\CreateOpportunityData;
use App\Domain\Projects\Opportunities\Models\Opportunity;
use App\Domain\Projects\Opportunities\Models\OpportunityItem;
use App\Domain\Stores\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Thunk\Verbs\Facades\Verbs;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
    $this->store = Store::factory()->create(['timezone' => 'UTC']);
});

it('rebuilds identical quantity-break discount projections after replay', function () {
    $category = DiscountCategory::factory()->create();
    DiscountRule::factory()->for($category)->create([
        'discount_percent' => '15.00',
        'criteria' => ['quantity_min' => 10],
        'rule_scope' => DiscountRuleScope::PerLine,
    ]);

    $member = Member::factory()->organisation()->create(['discount_category_id' => $category->id]);
    $product = Product::factory()->rental()->create();

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Replay quantity break',
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
        'quantity' => '9',
        'unit_price' => 1000,
    ]));

    $item = $opportunity->fresh('items')->items->firstOrFail();

    (new ChangeItemQuantity)($item, ChangeItemQuantityData::from(['quantity' => '10']));

    $before = [
        'discount' => OpportunityItem::query()->find($item->id)?->discount_percent,
        'total' => OpportunityItem::query()->find($item->id)?->total,
        'charge_total' => Opportunity::query()->find($opportunity->id)?->charge_total,
    ];

    Verbs::replay();

    $afterItem = OpportunityItem::query()->find($item->id);
    $afterOpportunity = Opportunity::query()->find($opportunity->id);

    expect((string) $afterItem?->discount_percent)->toBe((string) $before['discount'])
        ->and($afterItem?->total)->toBe($before['total'])
        ->and($afterOpportunity?->charge_total)->toBe($before['charge_total']);
});
