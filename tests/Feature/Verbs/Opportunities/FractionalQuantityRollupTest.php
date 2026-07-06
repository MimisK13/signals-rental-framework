<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\ConvertToOrder;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Enums\LineItemTransactionType;
use App\Enums\PricingRoundingDirection;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\Opportunities\OpportunityTotalsCalculator;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
    settings()->set('pricing.rounding_direction', PricingRoundingDirection::HalfUp->value);
    settings()->set('tax.unresolved_zone_fallback', 'seller_domestic');
});

it('rolls up fractional sale quantity totals without rounding quantity to an integer', function () {
    $zones = giWireZoneTaxInfrastructure();
    $store = Store::factory()->create(['tax_zone_id' => $zones['uk']->id, 'country_code' => 'GB']);
    $member = Member::factory()->organisation()->create([
        'sale_tax_class_id' => $zones['orgClass']->id,
        'tax_zone_id' => $zones['uk']->id,
    ]);
    $product = Product::factory()->sale()->create(['tax_class_id' => $zones['productClass']->id]);

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Fractional rollup',
        'member_id' => $member->id,
        'store_id' => $store->id,
    ]));
    (new ConvertToQuotation)(Opportunity::findOrFail($created->id));

    (new AddOpportunityItem)(Opportunity::findOrFail($created->id), AddOpportunityItemData::from([
        'name' => $product->name,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'quantity' => '1.5',
        'unit_price' => 10_000,
        'transaction_type' => LineItemTransactionType::Sale->value,
    ]));

    (new ConvertToOrder)(Opportunity::findOrFail($created->id));

    $opportunity = Opportunity::query()->findOrFail($created->id);
    app(OpportunityTotalsCalculator::class)->rollUp(
        $opportunity->fresh(['items.itemSurcharges', 'items.item', 'costs', 'member']),
    );

    $item = $opportunity->fresh()->items->first();

    expect($item->total)->toBe(15_000)
        ->and($opportunity->fresh()->charge_total)->toBe(15_000)
        ->and($opportunity->fresh()->charge_including_tax_total)->toBe(18_000);
});
