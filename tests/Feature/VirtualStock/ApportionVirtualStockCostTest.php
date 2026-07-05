<?php

use App\Actions\Opportunities\UpdateOpportunityCost;
use App\Actions\VirtualStock\ApportionVirtualStockCost;
use App\Actions\VirtualStock\CancelVirtualStockIntake;
use App\Actions\VirtualStock\ClearVirtualStockCostOverride;
use App\Actions\VirtualStock\ConfirmVirtualStockIntake;
use App\Actions\VirtualStock\ReceiveVirtualStockIntake;
use App\Actions\VirtualStock\ReturnVirtualStockIntake;
use App\Actions\VirtualStock\SetVirtualStockCostOverride;
use App\Data\Opportunities\UpdateOpportunityCostData;
use App\Data\VirtualStock\SetVirtualStockCostOverrideData;
use App\Enums\OpportunityCostType;
use App\Models\Opportunity;
use App\Models\OpportunityCost;
use App\Models\OpportunityItem;
use App\Models\ShortageResolution;
use App\Models\ShortageResolutionItem;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Thunk\Verbs\Facades\Verbs;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

/**
 * @return array<string, mixed>
 */
function apportionFixture(string $strategy = 'even_split'): array
{
    $intake = createTestVirtualStockIntake([
        'quantity' => 3,
        'unit_cost' => 1000,
        'available_from' => '2026-08-01T00:00:00Z',
        'available_until' => '2026-08-06T00:00:00Z',
    ]);

    $oppA = Opportunity::factory()->create([
        'starts_at' => Carbon::parse('2026-08-01T09:00:00Z'),
        'ends_at' => Carbon::parse('2026-08-05T17:00:00Z'),
    ]);
    $oppB = Opportunity::factory()->create([
        'starts_at' => Carbon::parse('2026-08-02T09:00:00Z'),
        'ends_at' => Carbon::parse('2026-08-04T17:00:00Z'),
    ]);

    $resolution = ShortageResolution::factory()->create([
        'virtual_stock_intake_id' => $intake->id,
        'metadata' => ['opportunity_id' => $oppA->id],
    ]);

    ShortageResolutionItem::factory()->create([
        'shortage_resolution_id' => $resolution->id,
        'opportunity_item_id' => OpportunityItem::factory()->for($oppA)->create()->id,
        'quantity_allocated' => 2,
    ]);
    ShortageResolutionItem::factory()->create([
        'shortage_resolution_id' => $resolution->id,
        'opportunity_item_id' => OpportunityItem::factory()->for($oppB)->create()->id,
        'quantity_allocated' => 1,
    ]);

    config(['shortages.cost_apportionment_strategy' => $strategy]);

    app(ConfirmVirtualStockIntake::class)($intake->refresh());

    return compact('intake', 'oppA', 'oppB', 'resolution');
}

it('fires cost events and stamps intake-linked subhire rows', function () {
    ['intake' => $intake, 'oppA' => $oppA, 'oppB' => $oppB] = apportionFixture('even_split');

    $costs = OpportunityCost::query()->where('virtual_stock_intake_id', $intake->id)->orderBy('opportunity_id')->get();

    expect($costs)->toHaveCount(2)
        ->and($costs->sum('amount'))->toBe(3000)
        ->and($costs->first()->virtual_stock_intake_id)->toBe($intake->id)
        ->and($costs->first()->cost_type)->toBe(OpportunityCostType::VirtualStock)
        ->and($costs->pluck('opportunity_id')->all())->toBe([$oppA->id, $oppB->id]);
});

it('re-apportionment is idempotent on a second run', function () {
    ['intake' => $intake] = apportionFixture('proportional_quantity');

    $beforeCount = OpportunityCost::query()->where('virtual_stock_intake_id', $intake->id)->count();
    $eventCount = verbEventCount();

    app(ApportionVirtualStockCost::class)($intake->refresh(), authorize: false);
    app(ApportionVirtualStockCost::class)($intake->refresh(), authorize: false);

    expect(OpportunityCost::query()->where('virtual_stock_intake_id', $intake->id)->count())->toBe($beforeCount)
        ->and(verbEventCount())->toBe($eventCount);
});

it('re-apportions when intake quantity changes', function () {
    ['intake' => $intake, 'oppA' => $oppA] = apportionFixture('primary_job');

    $intake->forceFill(['quantity' => 5])->saveQuietly();

    app(ApportionVirtualStockCost::class)($intake->refresh(), authorize: false);

    $primaryCost = OpportunityCost::query()
        ->where('virtual_stock_intake_id', $intake->id)
        ->where('opportunity_id', $oppA->id)
        ->first();

    expect($primaryCost?->amount)->toBe(5000);
});

it('removes apportioned costs on return and cancel', function () {
    ['intake' => $intake] = apportionFixture('even_split');

    app(ReceiveVirtualStockIntake::class)($intake->refresh());
    app(ReturnVirtualStockIntake::class)($intake->refresh());
    expect(OpportunityCost::query()->where('virtual_stock_intake_id', $intake->id)->count())->toBe(0);

    ['intake' => $intake2] = apportionFixture('even_split');
    app(CancelVirtualStockIntake::class)($intake2->refresh());
    expect(OpportunityCost::query()->where('virtual_stock_intake_id', $intake2->id)->count())->toBe(0);
});

it('warns on manual strategy mismatch without blocking apportionment', function () {
    ['intake' => $intake, 'oppA' => $oppA] = apportionFixture('manual');

    app(SetVirtualStockCostOverride::class)(
        $intake,
        SetVirtualStockCostOverrideData::from([
            'opportunity_id' => $oppA->id,
            'unit_cost_override' => 100,
        ]),
    );

    $result = app(ApportionVirtualStockCost::class)($intake->refresh(), 'manual');

    expect($result->manual_mismatch_warning)->toBeTrue()
        ->and($result->intake_total_minor)->toBe(3000)
        ->and($result->allocated_total_minor)->toBe(200);
});

it('rebuilds opportunity_costs byte-identically after truncate and replay', function () {
    ['intake' => $intake] = apportionFixture('proportional_quantity');

    $costsBefore = OpportunityCost::query()->orderBy('id')->get()->map(
        fn (OpportunityCost $row): array => $row->getAttributes(),
    )->all();

    $eventCountBeforeReplay = verbEventCount();

    OpportunityCost::query()->delete();

    Verbs::replay();

    expect(verbEventCount())->toBe($eventCountBeforeReplay);

    $costsAfter = OpportunityCost::query()->orderBy('id')->get()->map(
        fn (OpportunityCost $row): array => $row->getAttributes(),
    )->all();

    expect($costsAfter)->toBe($costsBefore);
});

it('preserves the intake link when a user edits an apportioned cost', function () {
    ['intake' => $intake] = apportionFixture('even_split');

    $cost = OpportunityCost::query()->where('virtual_stock_intake_id', $intake->id)->firstOrFail();

    (new UpdateOpportunityCost)($cost, UpdateOpportunityCostData::from([
        'description' => 'Edited by a user after apportionment',
    ]));

    expect($cost->refresh()->virtual_stock_intake_id)->toBe($intake->id);

    $beforeCount = OpportunityCost::query()->where('virtual_stock_intake_id', $intake->id)->count();

    app(ApportionVirtualStockCost::class)($intake->refresh(), authorize: false);

    expect(OpportunityCost::query()->where('virtual_stock_intake_id', $intake->id)->count())->toBe($beforeCount);
});

it('requires costs.view permission', function () {
    ['intake' => $intake] = apportionFixture('even_split');

    $viewer = User::factory()->create();
    $viewer->givePermissionTo(['virtual_stock.view', 'virtual_stock.edit']);

    $this->actingAs($viewer);

    expect(fn () => app(ApportionVirtualStockCost::class)($intake->refresh()))
        ->toThrow(AuthorizationException::class);
});

it('clears manual overrides and re-apportions', function () {
    ['intake' => $intake, 'oppA' => $oppA] = apportionFixture('manual');

    app(SetVirtualStockCostOverride::class)(
        $intake,
        SetVirtualStockCostOverrideData::from([
            'opportunity_id' => $oppA->id,
            'unit_cost_override' => 400,
        ]),
    );

    app(ClearVirtualStockCostOverride::class)($intake->refresh(), $oppA);

    $cost = OpportunityCost::query()
        ->where('virtual_stock_intake_id', $intake->id)
        ->where('opportunity_id', $oppA->id)
        ->first();

    expect($cost)->toBeNull();
});
