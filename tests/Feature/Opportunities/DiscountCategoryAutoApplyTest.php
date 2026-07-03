<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\CreateOpportunity;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Models\ActionLog;
use App\Models\DiscountCategory;
use App\Models\DiscountRule;
use App\Models\Member;
use App\Models\Opportunity;
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

it('auto-applies the member discount category when a line item is added', function () {
    $category = DiscountCategory::factory()->create(['name' => 'Trade']);
    DiscountRule::factory()->for($category)->blanket()->create([
        'discount_percent' => '15.00',
    ]);

    $member = Member::factory()->organisation()->create([
        'discount_category_id' => $category->id,
    ]);
    $product = Product::factory()->rental()->create();

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Discount category E2E',
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
    ]));

    $item = $opportunity->fresh('items')->items->firstOrFail();

    expect($item->discount_percent)->toBe('15.00')
        ->and($item->is_discount_manual)->toBeFalse();

    expect(ActionLog::query()
        ->where('auditable_type', Opportunity::class)
        ->where('auditable_id', $opportunity->id)
        ->where('action', 'opportunity.discount_category_applied')
        ->exists())->toBeTrue();
});

it('picks the highest matching rule when multiple rules apply', function () {
    $category = DiscountCategory::factory()->create();
    DiscountRule::factory()->for($category)->create(['discount_percent' => '10.00', 'criteria' => []]);
    DiscountRule::factory()->for($category)->create(['discount_percent' => '15.00', 'criteria' => []]);
    DiscountRule::factory()->for($category)->create(['discount_percent' => '20.00', 'criteria' => []]);

    $member = Member::factory()->organisation()->create([
        'discount_category_id' => $category->id,
    ]);
    $product = Product::factory()->rental()->create();

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Highest wins',
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
    ]));

    expect($opportunity->fresh('items')->items->first()->discount_percent)->toBe('20.00');
});
