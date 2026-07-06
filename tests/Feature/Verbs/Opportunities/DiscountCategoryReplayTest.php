<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\ApplyDiscountCategory;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\SetItemDiscount;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\ApplyDiscountCategoryData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\SetItemDiscountData;
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
use Thunk\Verbs\Facades\Verbs;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
    $this->store = Store::factory()->create(['timezone' => 'UTC']);
});

it('rebuilds identical line discounts and totals after replay', function () {
    $category = DiscountCategory::factory()->create();
    DiscountRule::factory()->for($category)->blanket()->create(['discount_percent' => '12.50']);

    $member = Member::factory()->organisation()->create([
        'discount_category_id' => $category->id,
    ]);
    $product = Product::factory()->rental()->create();

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Replay discount slice',
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
        'quantity' => '2',
    ]));

    $item = $opportunity->fresh('items')->items->firstOrFail();
    (new SetItemDiscount)($item, SetItemDiscountData::from(['discount_percent' => '7.50']));

    (new ApplyDiscountCategory)($opportunity->fresh(), ApplyDiscountCategoryData::from([
        'opportunity_item_ids' => [$item->fresh()->id],
        'reset_manual' => true,
    ]));

    $opportunity->refresh();
    $item->refresh();

    $snapshot = [
        'item' => [
            'discount_percent' => $item->discount_percent,
            'is_discount_manual' => $item->is_discount_manual,
            'total' => $item->total,
        ],
        'opportunity' => [
            'charge_total' => $opportunity->charge_total,
            'charge_excluding_tax_total' => $opportunity->charge_excluding_tax_total,
            'tax_total' => $opportunity->tax_total,
        ],
    ];

    Verbs::replay();

    $item = OpportunityItem::query()->findOrFail($item->id);
    $opportunity = Opportunity::query()->findOrFail($opportunity->id);

    expect([
        'discount_percent' => $item->discount_percent,
        'is_discount_manual' => $item->is_discount_manual,
        'total' => $item->total,
    ])->toBe($snapshot['item'])
        ->and([
            'charge_total' => $opportunity->charge_total,
            'charge_excluding_tax_total' => $opportunity->charge_excluding_tax_total,
            'tax_total' => $opportunity->tax_total,
        ])->toBe($snapshot['opportunity']);
});
