<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\CreateOpportunity;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Enums\OpportunityItemType;
use App\Models\Accessory;
use App\Models\DiscountCategory;
use App\Models\DiscountRule;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\Pricing\DiscountCategoryApplier;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
    $this->store = Store::factory()->create(['timezone' => 'UTC']);
});

function discountApplyOpportunity(Member $member): Opportunity
{
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Discount apply atomicity',
        'member_id' => $member->id,
        'store_id' => test()->store->id,
        'starts_at' => '2026-07-01T09:00:00Z',
        'ends_at' => '2026-07-05T17:00:00Z',
    ]));

    return Opportunity::query()->whereKey($created->id)->firstOrFail();
}

it('still applies category discounts to materialised accessories when the principal has a manual discount', function () {
    $category = DiscountCategory::factory()->create();
    DiscountRule::factory()->for($category)->blanket()->create(['discount_percent' => '12.00']);

    $member = Member::factory()->organisation()->create([
        'discount_category_id' => $category->id,
    ]);
    $opportunity = discountApplyOpportunity($member);

    $product = Product::factory()->rental()->create(['name' => 'Speaker']);
    $included = Product::factory()->rental()->create(['name' => 'Included Cable']);

    Accessory::factory()->create([
        'product_id' => $product->id,
        'accessory_product_id' => $included->id,
        'quantity' => '1',
        'included' => true,
    ]);

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => $product->name,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'quantity' => '1',
        'discount_percent' => '5.00',
    ]));

    $items = $opportunity->refresh()->items()->orderBy('path')->get();

    expect($items)->toHaveCount(2);

    $principal = $items->firstWhere('item_type', OpportunityItemType::Product);
    $accessory = $items->firstWhere('item_type', OpportunityItemType::Accessory);

    expect($principal)->not->toBeNull()
        ->and($principal->discount_percent)->toBe('5.00')
        ->and($principal->is_discount_manual)->toBeTrue()
        ->and($accessory)->not->toBeNull()
        ->and($accessory->discount_percent)->toBe('12.00')
        ->and($accessory->is_discount_manual)->toBeFalse();
});

it('rolls back item creation when discount application fails inside the same commit boundary', function () {
    $category = DiscountCategory::factory()->create();
    DiscountRule::factory()->for($category)->blanket()->create(['discount_percent' => '10.00']);

    $member = Member::factory()->organisation()->create([
        'discount_category_id' => $category->id,
    ]);
    $opportunity = discountApplyOpportunity($member);
    $product = Product::factory()->rental()->create();

    $this->mock(DiscountCategoryApplier::class, function ($mock): void {
        $mock->shouldReceive('apply')->once()->andThrow(new RuntimeException('Discount apply failed'));
    });

    expect(fn () => (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => $product->name,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'quantity' => '1',
    ])))->toThrow(RuntimeException::class, 'Discount apply failed');

    expect(OpportunityItem::query()->where('opportunity_id', $opportunity->id)->count())->toBe(0);
});
