<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\OverrideOpportunityItemSurcharge;
use App\Actions\Pricing\DeleteSurcharge;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\OpportunityItemSurchargeData;
use App\Data\Opportunities\OverrideOpportunityItemSurchargeData;
use App\Enums\BasePeriod;
use App\Enums\CalculationStrategyType;
use App\Enums\PricingRoundingDirection;
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
use Illuminate\Support\Facades\DB;
use Thunk\Verbs\Facades\Verbs;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
    settings()->set('pricing.rounding_direction', PricingRoundingDirection::HalfUp->value);
    $this->store = Store::factory()->create(['timezone' => 'UTC']);
});

function snapshotWireFlatProduct(int $priceMinor = 10_000): Product
{
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
        'price' => $priceMinor,
    ]);

    return $product;
}

/** @return array{0: Opportunity, 1: OpportunityItem} */
function snapshotPricedOpportunity(Product $product): array
{
    $member = Member::factory()->organisation()->create();

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Surcharge snapshot slice',
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
        'quantity' => '1',
    ]));

    return [$opportunity, $opportunity->fresh('items')->items->firstOrFail()];
}

it('keeps applied surcharge rows with snapshots when the source surcharge is deleted', function () {
    $product = snapshotWireFlatProduct();
    $group = SurchargeGroup::factory()->defaultGroup()->create();
    $surcharge = Surcharge::factory()->for($group, 'surchargeGroup')->percent('10.00')->create([
        'name' => 'Equipment insurance',
    ]);

    [$opportunity, $item] = snapshotPricedOpportunity($product);

    $row = OpportunityItemSurcharge::query()
        ->where('opportunity_item_id', $item->id)
        ->where('surcharge_id', $surcharge->id)
        ->firstOrFail();

    expect($row->name)->toBe('Equipment insurance')
        ->and($row->surcharge_type)->toBe('percent')
        ->and((string) $row->percent_value)->toBe('10.00');

    $itemTotalBefore = $item->fresh()->total;
    $surchargeTotalBefore = $item->fresh()->surcharge_total;
    $chargeTotalBefore = $opportunity->fresh()->charge_total;

    (new DeleteSurcharge)($surcharge);

    $row->refresh();
    $item->refresh();
    $opportunity->refresh();

    expect(Surcharge::query()->whereKey($surcharge->id)->exists())->toBeFalse()
        ->and(OpportunityItemSurcharge::query()->whereKey($row->id)->exists())->toBeTrue()
        ->and($row->surcharge_id)->toBeNull()
        ->and($row->name)->toBe('Equipment insurance')
        ->and($row->surcharge_type)->toBe('percent')
        ->and($item->surcharge_total)->toBe($surchargeTotalBefore)
        ->and($item->total)->toBe($itemTotalBefore)
        ->and($opportunity->charge_total)->toBe($chargeTotalBefore);

    $dto = OpportunityItemSurchargeData::fromModel($row);

    expect($dto->name)->toBe('Equipment insurance')
        ->and($dto->surcharge_type)->toBe('percent')
        ->and($dto->percent_value)->toBe('10.00')
        ->and($dto->amount)->toBe('10.00');
});

it('backfills snapshot columns for legacy rows that still reference a live surcharge', function () {
    $product = snapshotWireFlatProduct();
    $group = SurchargeGroup::factory()->defaultGroup()->create();
    $surcharge = Surcharge::factory()->for($group, 'surchargeGroup')->fixed(750)->create([
        'name' => 'Prep fee',
    ]);

    [$opportunity, $item] = snapshotPricedOpportunity($product);

    $row = OpportunityItemSurcharge::query()
        ->where('opportunity_item_id', $item->id)
        ->where('surcharge_id', $surcharge->id)
        ->firstOrFail();

    DB::table('opportunity_item_surcharges')
        ->where('id', $row->id)
        ->update([
            'name' => null,
            'surcharge_type' => null,
            'percent_value' => null,
            'fixed_value_minor' => null,
        ]);

    DB::statement(<<<'SQL'
        UPDATE opportunity_item_surcharges AS ois
        SET
            name = s.name,
            surcharge_type = CASE
                WHEN s.percent_value IS NOT NULL THEN 'percent'
                ELSE 'fixed'
            END,
            percent_value = s.percent_value,
            fixed_value_minor = s.fixed_value_minor
        FROM surcharges AS s
        WHERE ois.surcharge_id = s.id
          AND ois.name IS NULL
        SQL);

    $row->refresh();

    expect($row->name)->toBe('Prep fee')
        ->and($row->surcharge_type)->toBe('fixed')
        ->and($row->fixed_value_minor)->toBe(750);
});

it('rebuilds identical surcharge snapshot projections after replay', function () {
    $product = snapshotWireFlatProduct(8000);
    $group = SurchargeGroup::factory()->defaultGroup()->create();
    $surcharge = Surcharge::factory()->for($group, 'surchargeGroup')->percent('5.00')->create([
        'name' => 'Replay surcharge',
    ]);

    [$opportunity, $item] = snapshotPricedOpportunity($product);

    (new OverrideOpportunityItemSurcharge)($opportunity->fresh(), $item->fresh(), OverrideOpportunityItemSurchargeData::from([
        'surcharge_id' => $surcharge->id,
        'amount_minor' => 999,
    ]));

    $item->refresh();

    $snapshot = OpportunityItemSurcharge::query()
        ->where('opportunity_item_id', $item->id)
        ->orderBy('surcharge_id')
        ->get([
            'surcharge_id',
            'name',
            'surcharge_type',
            'percent_value',
            'fixed_value_minor',
            'amount',
            'is_manual_override',
            'is_disabled',
        ])
        ->map->only([
            'surcharge_id',
            'name',
            'surcharge_type',
            'percent_value',
            'fixed_value_minor',
            'amount',
            'is_manual_override',
            'is_disabled',
        ])
        ->all();

    Verbs::replay();

    $replayed = OpportunityItemSurcharge::query()
        ->where('opportunity_item_id', $item->id)
        ->orderBy('surcharge_id')
        ->get([
            'surcharge_id',
            'name',
            'surcharge_type',
            'percent_value',
            'fixed_value_minor',
            'amount',
            'is_manual_override',
            'is_disabled',
        ])
        ->map->only([
            'surcharge_id',
            'name',
            'surcharge_type',
            'percent_value',
            'fixed_value_minor',
            'amount',
            'is_manual_override',
            'is_disabled',
        ])
        ->all();

    expect($replayed)->toBe($snapshot)
        ->and(collect($replayed)->first()['name'])->toBe('Replay surcharge');
});
