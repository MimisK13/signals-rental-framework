<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\OverrideOpportunityItemSurcharge;
use App\Actions\Opportunities\ToggleOpportunitySurchargeGroup;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\OverrideOpportunityItemSurchargeData;
use App\Data\Opportunities\ToggleOpportunitySurchargeGroupData;
use App\Enums\BasePeriod;
use App\Enums\CalculationStrategyType;
use App\Enums\RateTransactionType;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\OpportunityItemSurcharge;
use App\Models\Product;
use App\Models\ProductRate;
use App\Models\RateDefinition;
use App\Models\Store;
use App\Models\Surcharge;
use App\Models\SurchargeGroup;
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

it('rebuilds identical surcharge projections and totals after replay', function () {
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
        'price' => 8000,
    ]);

    $group = SurchargeGroup::factory()->defaultGroup()->create();
    $surcharge = Surcharge::factory()->for($group, 'surchargeGroup')->percent('5.00')->create();
    $optional = SurchargeGroup::factory()->create(['is_default' => false]);
    Surcharge::factory()->for($optional, 'surchargeGroup')->fixed(200)->create();

    $member = Member::factory()->organisation()->create();

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Replay surcharge slice',
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
        'quantity' => '1',
    ]));

    $item = $opportunity->fresh('items')->items->firstOrFail();

    (new ToggleOpportunitySurchargeGroup)($opportunity->fresh(), ToggleOpportunitySurchargeGroupData::from([
        'surcharge_group_id' => $optional->id,
        'is_active' => true,
    ]));

    (new OverrideOpportunityItemSurcharge)($opportunity->fresh(), $item->fresh(), OverrideOpportunityItemSurchargeData::from([
        'surcharge_id' => $surcharge->id,
        'amount_minor' => 999,
    ]));

    $opportunity->refresh();
    $item->refresh();

    $snapshot = [
        'item' => [
            'total' => $item->total,
            'surcharge_total' => $item->surcharge_total,
        ],
        'opportunity' => [
            'charge_total' => $opportunity->charge_total,
            'tax_total' => $opportunity->tax_total,
        ],
        'surcharge_rows' => OpportunityItemSurcharge::query()
            ->where('opportunity_item_id', $item->id)
            ->orderBy('surcharge_id')
            ->get(['surcharge_id', 'amount', 'is_manual_override', 'is_disabled'])
            ->map->only(['surcharge_id', 'amount', 'is_manual_override', 'is_disabled'])
            ->all(),
    ];

    Verbs::replay();

    $item = OpportunityItem::query()->findOrFail($item->id);
    $opportunity = Opportunity::query()->findOrFail($opportunity->id);

    expect([
        'total' => $item->total,
        'surcharge_total' => $item->surcharge_total,
    ])->toBe($snapshot['item'])
        ->and([
            'charge_total' => $opportunity->charge_total,
            'tax_total' => $opportunity->tax_total,
        ])->toBe($snapshot['opportunity']);
});
