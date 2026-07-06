<?php

use App\Actions\VirtualStock\ConfirmVirtualStockIntake;
use App\Actions\VirtualStock\ReceiveVirtualStockIntake;
use App\Actions\VirtualStock\ReturnVirtualStockIntake;
use App\Data\VirtualStock\ReturnVirtualStockIntakeData;
use App\Enums\DemandPhase;
use App\Models\Demand;
use App\Models\Opportunity;
use App\Models\OpportunityCost;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Models\ShortageResolution;
use App\Models\ShortageResolutionItem;
use App\Models\User;
use App\Models\VirtualStockIntake;
use App\Services\VirtualStock\VirtualStockIntakeProfitAndLossBuilder;
use App\Services\VirtualStock\VirtualStockIntakeReturnGuard;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Volt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

/**
 * @param  array<string, mixed>  $intakeOverrides
 * @param  array<string, mixed>  $itemOverrides
 * @return array<string, mixed>
 */
function returnWorkflowFixture(array $intakeOverrides = [], array $itemOverrides = []): array
{
    $intake = createTestVirtualStockIntake([
        'quantity' => 2,
        'unit_cost' => 1000,
        'available_from' => '2026-08-01T00:00:00Z',
        'available_until' => '2026-08-06T00:00:00Z',
        ...$intakeOverrides,
    ]);

    $opportunity = Opportunity::factory()->create();
    $item = OpportunityItem::factory()->for($opportunity)->create([
        'quantity' => '2.00',
        'total' => 5000,
        'dispatched_quantity' => '2.00',
        'returned_quantity' => '0.00',
        ...$itemOverrides,
    ]);

    $resolution = ShortageResolution::factory()->create([
        'virtual_stock_intake_id' => $intake->id,
        'metadata' => ['opportunity_id' => $opportunity->id],
    ]);

    ShortageResolutionItem::factory()->create([
        'shortage_resolution_id' => $resolution->id,
        'opportunity_item_id' => $item->id,
        'quantity_allocated' => 2,
    ]);

    app(ConfirmVirtualStockIntake::class)($intake->refresh());
    app(ReceiveVirtualStockIntake::class)($intake->refresh());

    return compact('intake', 'opportunity', 'item', 'resolution');
}

it('returns an intake, voids supply demand, and removes apportioned costs', function () {
    ['intake' => $intake] = returnWorkflowFixture(itemOverrides: [
        'returned_quantity' => '2.00',
    ]);

    expect(OpportunityCost::query()->where('virtual_stock_intake_id', $intake->id)->count())->toBeGreaterThan(0);

    $demandId = $intake->refresh()->demand_id;
    expect($demandId)->not->toBeNull();

    Carbon::setTestNow(Carbon::parse('2026-08-05T10:00:00Z'));

    app(ReturnVirtualStockIntake::class)($intake->refresh());

    $intake->refresh();
    expect($intake->status)->toBe('returned')
        ->and($intake->returned_at)->not->toBeNull()
        ->and($intake->isLateReturn())->toBeFalse()
        ->and(OpportunityCost::query()->where('virtual_stock_intake_id', $intake->id)->count())->toBe(0);

    $demand = Demand::query()->findOrFail($demandId);
    expect($demand->phase)->toBe(DemandPhase::Void)
        ->and($demand->is_active)->toBeFalse();

    Carbon::setTestNow();
});

it('detects late returns after available_until and after purchase order ends_at', function () {
    $po = createTestPurchaseOrder([
        'po_type' => 'sub_hire',
        'ends_at' => '2026-08-04T23:59:59Z',
        'lines' => [[
            'product_id' => Product::factory()->create()->id,
            'description' => 'Sub-hired kit',
            'quantity' => '2.00',
            'unit_cost' => 1000,
        ]],
    ]);

    ['intake' => $intake] = returnWorkflowFixture([
        'purchase_order_id' => $po->id,
        'purchase_order_item_id' => $po->items()->firstOrFail()->id,
        'available_until' => '2026-08-10T00:00:00Z',
    ]);

    Carbon::setTestNow(Carbon::parse('2026-08-05T10:00:00Z'));
    app(ReturnVirtualStockIntake::class)($intake->refresh(), new ReturnVirtualStockIntakeData(force: true));
    expect($intake->refresh()->isLateReturn())->toBeTrue();

    ['intake' => $intakeLateWindow] = returnWorkflowFixture([
        'available_until' => '2026-08-04T00:00:00Z',
    ]);

    Carbon::setTestNow(Carbon::parse('2026-08-05T10:00:00Z'));
    app(ReturnVirtualStockIntake::class)($intakeLateWindow->refresh(), new ReturnVirtualStockIntakeData(force: true));
    expect($intakeLateWindow->refresh()->isLateReturn())->toBeTrue();

    Carbon::setTestNow();
});

it('blocks intake return when allocations are outstanding unless forced', function () {
    ['intake' => $intake, 'item' => $item] = returnWorkflowFixture();

    expect(app(VirtualStockIntakeReturnGuard::class)->hasOutstandingAllocations($intake))->toBeTrue();

    expect(fn () => app(ReturnVirtualStockIntake::class)($intake->refresh()))
        ->toThrow(ValidationException::class);

    app(ReturnVirtualStockIntake::class)($intake->refresh(), new ReturnVirtualStockIntakeData(force: true));

    expect($intake->refresh()->status)->toBe('returned');
});

it('does not mutate opportunity allocation state when an intake is force-returned', function () {
    ['intake' => $intake, 'item' => $item] = returnWorkflowFixture();

    $itemSnapshot = $item->refresh()->only([
        'dispatched_quantity',
        'returned_quantity',
        'quantity',
    ]);

    $itemDemandCount = Demand::query()
        ->where('source_type', 'opportunity_item')
        ->where('source_id', $item->id)
        ->count();

    app(ReturnVirtualStockIntake::class)($intake->refresh(), new ReturnVirtualStockIntakeData(force: true));

    $item->refresh();
    expect($item->only(['dispatched_quantity', 'returned_quantity', 'quantity']))->toBe($itemSnapshot)
        ->and(Demand::query()
            ->where('source_type', 'opportunity_item')
            ->where('source_id', $item->id)
            ->count())->toBe($itemDemandCount);
});

it('builds P&L rows with charge-out revenue and margin per opportunity', function () {
    ['intake' => $intake, 'opportunity' => $opportunity] = returnWorkflowFixture();

    $result = app(VirtualStockIntakeProfitAndLossBuilder::class)->build($intake->refresh(), 'even_split');

    expect($result['intake_total_minor'])->toBe(2000)
        ->and($result['rows'])->toHaveCount(1)
        ->and($result['rows'][0]['opportunity_id'])->toBe($opportunity->id)
        ->and($result['rows'][0]['revenue_minor'])->toBe(5000)
        ->and($result['rows'][0]['apportioned_cost_minor'])->toBe(2000)
        ->and($result['rows'][0]['margin_minor'])->toBe(3000);
});

it('filters late returns via API ransack flag', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-05T10:00:00Z'));

    ['intake' => $late] = returnWorkflowFixture(['available_until' => '2026-08-04T00:00:00Z']);
    app(ReturnVirtualStockIntake::class)($late->refresh(), new ReturnVirtualStockIntakeData(force: true));

    ['intake' => $onTime] = returnWorkflowFixture(['available_until' => '2026-08-10T00:00:00Z']);
    app(ReturnVirtualStockIntake::class)($onTime->refresh(), new ReturnVirtualStockIntakeData(force: true));

    $token = auth()->user()->createToken('test', ['virtual_stock:read', 'virtual_stock:write'])->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/virtual_stock_intakes?q[is_late_return_true]=1')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('virtual_stock_intakes.0.id', $late->id);

    Carbon::setTestNow();
});

it('accepts force flag on the intake return API', function () {
    ['intake' => $intake] = returnWorkflowFixture();
    $token = auth()->user()->createToken('test', ['virtual_stock:read', 'virtual_stock:write'])->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/virtual_stock_intakes/{$intake->id}/return", ['force' => true])
        ->assertOk()
        ->assertJsonPath('virtual_stock_intake.status', 'returned');
});

it('renders return-to-supplier affordances on virtual stock and purchase order pages', function () {
    $po = createTestPurchaseOrder([
        'po_type' => 'sub_hire',
        'lines' => [[
            'product_id' => Product::factory()->create()->id,
            'description' => 'Sub-hired kit',
            'quantity' => '2.00',
            'unit_cost' => 1000,
        ]],
    ]);

    $intake = createTestVirtualStockIntake([
        'purchase_order_id' => $po->id,
        'purchase_order_item_id' => $po->items()->firstOrFail()->id,
    ]);

    app(ConfirmVirtualStockIntake::class)($intake);
    app(ReceiveVirtualStockIntake::class)($intake->refresh());

    Volt::test('virtual-stock.show', ['virtualStockIntake' => $intake->refresh()])
        ->assertSee('Return to supplier')
        ->assertSee('Cost apportionment');

    Volt::test('purchase-orders.show', ['purchaseOrder' => $po->refresh()])
        ->assertSee('Virtual stock intakes')
        ->assertSee($intake->number)
        ->assertSee('Return to supplier');
});

it('scopes late and on-time intakes for listing', function () {
    $late = VirtualStockIntake::factory()->create([
        'returned_at' => Carbon::parse('2026-08-07T00:00:00Z'),
        'available_until' => Carbon::parse('2026-08-06T00:00:00Z'),
    ]);

    $onTime = VirtualStockIntake::factory()->create([
        'returned_at' => Carbon::parse('2026-08-05T00:00:00Z'),
        'available_until' => Carbon::parse('2026-08-06T00:00:00Z'),
    ]);

    expect(VirtualStockIntake::query()->lateReturn(true)->pluck('id')->all())->toContain($late->id)
        ->and(VirtualStockIntake::query()->lateReturn(true)->pluck('id')->all())->not->toContain($onTime->id)
        ->and(VirtualStockIntake::query()->lateReturn(false)->pluck('id')->all())->toContain($onTime->id);
});
