<?php

use App\Domain\Products\Models\Product;
use App\Domain\Projects\Opportunities\Actions\AddOpportunityItem;
use App\Domain\Projects\Opportunities\Actions\ApplyDiscountCategory;
use App\Domain\Projects\Opportunities\Actions\ChangeItemQuantity;
use App\Domain\Projects\Opportunities\Actions\CreateOpportunity;
use App\Domain\Projects\Opportunities\Actions\SetItemDiscount;
use App\Domain\Projects\Opportunities\Data\AddOpportunityItemData;
use App\Domain\Projects\Opportunities\Data\ApplyDiscountCategoryData;
use App\Domain\Projects\Opportunities\Data\ChangeItemQuantityData;
use App\Domain\Projects\Opportunities\Data\CreateOpportunityData;
use App\Domain\Projects\Opportunities\Data\SetItemDiscountData;
use App\Domain\Projects\Opportunities\Models\Opportunity;
use App\Domain\Projects\Opportunities\Models\OpportunityItem;
use App\Models\ActionLog;
use App\Models\DiscountCategory;
use App\Models\DiscountRule;
use App\Models\Member;
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
 * @return array{opportunity: Opportunity, item: OpportunityItem, category: DiscountCategory}
 */
function manualOverrideFixture(): array
{
    $category = DiscountCategory::factory()->create();
    DiscountRule::factory()->for($category)->blanket()->create(['discount_percent' => '15.00']);

    $member = Member::factory()->organisation()->create([
        'discount_category_id' => $category->id,
    ]);
    $product = Product::factory()->rental()->create();

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Manual override',
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
        'quantity' => '2',
    ]));

    $item = $opportunity->fresh('items')->items->firstOrFail();

    (new SetItemDiscount)($item, SetItemDiscountData::from(['discount_percent' => '5.00']));
    $item->refresh();

    return compact('opportunity', 'item', 'category');
}

it('marks manual discounts and records the override audit event', function () {
    ['item' => $item] = manualOverrideFixture();

    expect($item->discount_percent)->toBe('5.00')
        ->and($item->is_discount_manual)->toBeTrue();

    expect(ActionLog::query()
        ->where('action', 'opportunity.discount_manually_overridden')
        ->where('auditable_id', $item->opportunity_id)
        ->exists())->toBeTrue();
});

it('does not clobber a manual discount on re-apply or quantity recalc', function () {
    ['opportunity' => $opportunity, 'item' => $item] = manualOverrideFixture();

    (new ApplyDiscountCategory)($opportunity->fresh(), ApplyDiscountCategoryData::from([]));

    $item->refresh();
    expect($item->discount_percent)->toBe('5.00')
        ->and($item->is_discount_manual)->toBeTrue();

    (new ChangeItemQuantity)($item, ChangeItemQuantityData::from(['quantity' => '3']));

    $item->refresh();
    expect($item->discount_percent)->toBe('5.00')
        ->and($item->is_discount_manual)->toBeTrue();
});

it('re-applies the category discount when reset_manual is true', function () {
    ['opportunity' => $opportunity, 'item' => $item] = manualOverrideFixture();

    (new ApplyDiscountCategory)($opportunity->fresh(), ApplyDiscountCategoryData::from([
        'opportunity_item_ids' => [$item->id],
        'reset_manual' => true,
    ]));

    $item->refresh();

    expect($item->discount_percent)->toBe('15.00')
        ->and($item->is_discount_manual)->toBeFalse();
});
