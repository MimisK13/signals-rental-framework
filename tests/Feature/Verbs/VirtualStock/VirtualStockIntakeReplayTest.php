<?php

use App\Actions\VirtualStock\CancelVirtualStockIntake;
use App\Actions\VirtualStock\ConfirmVirtualStockIntake;
use App\Actions\VirtualStock\CreateVirtualStockIntake;
use App\Actions\VirtualStock\DeleteDraftVirtualStockIntake;
use App\Actions\VirtualStock\ReceiveVirtualStockIntake;
use App\Actions\VirtualStock\ReturnVirtualStockIntake;
use App\Contracts\Availability\AvailabilityResolutionProvider;
use App\Data\VirtualStock\CreateVirtualStockIntakeData;
use App\Enums\AvailabilityResolution;
use App\Enums\DemandPhase;
use App\Models\AvailabilitySnapshot;
use App\Models\Demand;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use App\Models\VirtualStockIntake;
use App\Services\Availability\RecalculationPipeline;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Thunk\Verbs\Facades\Verbs;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
    Carbon::setTestNow(Carbon::parse('2026-07-04T12:00:00Z'));
    Queue::fake();

    $this->app->bind(AvailabilityResolutionProvider::class, fn () => new class implements AvailabilityResolutionProvider
    {
        public function resolve(): AvailabilityResolution
        {
            return AvailabilityResolution::Daily;
        }
    });
});

afterEach(function () {
    Carbon::setTestNow();
});

it('reconstructs virtual stock intake and demand projections byte-identically after replay', function () {
    $store = Store::factory()->create(['timezone' => 'UTC']);
    $product = Product::factory()->bulk()->create();

    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'quantity_held' => 2,
    ]);

    $created = (new CreateVirtualStockIntake)(CreateVirtualStockIntakeData::from([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'quantity' => 4,
        'available_from' => '2026-08-01T00:00:00Z',
        'available_until' => '2026-08-06T00:00:00Z',
        'unit_cost' => 1500,
    ]));

    $intake = VirtualStockIntake::query()->findOrFail($created->id);

    (new ConfirmVirtualStockIntake)($intake);
    (new ReceiveVirtualStockIntake)($intake->refresh());
    (new ReturnVirtualStockIntake)($intake->refresh());

    $intakesBefore = VirtualStockIntake::withTrashed()->orderBy('id')->get()->map(
        fn (VirtualStockIntake $row): array => $row->getAttributes(),
    )->all();

    $demandsBefore = Demand::query()->orderBy('id')->get()->map(
        fn (Demand $row): array => $row->getAttributes(),
    )->all();

    $eventCountBeforeReplay = verbEventCount();

    Demand::query()->delete();
    VirtualStockIntake::withTrashed()->forceDelete();

    Verbs::replay();

    expect(verbEventCount())->toBe($eventCountBeforeReplay);

    $intakesAfter = VirtualStockIntake::withTrashed()->orderBy('id')->get()->map(
        fn (VirtualStockIntake $row): array => $row->getAttributes(),
    )->all();

    $demandsAfter = Demand::query()->orderBy('id')->get()->map(
        fn (Demand $row): array => $row->getAttributes(),
    )->all();

    expect($intakesAfter)->toBe($intakesBefore)
        ->and($demandsAfter)->toBe($demandsBefore);
});

it('keeps deleted draft intakes soft-deleted after replay', function () {
    $intake = createTestVirtualStockIntake();

    (new DeleteDraftVirtualStockIntake)($intake);

    $eventCountBeforeReplay = verbEventCount();

    VirtualStockIntake::withTrashed()->whereKey($intake->id)->forceDelete();

    Verbs::replay();

    expect(verbEventCount())->toBe($eventCountBeforeReplay)
        ->and(VirtualStockIntake::withTrashed()->find($intake->id)?->deleted_at)->not->toBeNull();
});

it('creates negative supply demand on confirm and voids it on return and cancel', function () {
    $store = Store::factory()->create(['timezone' => 'UTC']);
    $product = Product::factory()->bulk()->create();

    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'quantity_held' => 2,
    ]);

    $pipeline = app(RecalculationPipeline::class);

    $intake = createTestVirtualStockIntake([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'quantity' => 3,
        'available_from' => '2026-08-02T00:00:00Z',
        'available_until' => '2026-08-05T00:00:00Z',
    ]);

    $pipeline->recalculate(
        $product->id,
        $store->id,
        Carbon::parse('2026-08-02T00:00:00Z'),
        Carbon::parse('2026-08-06T00:00:00Z'),
    );

    $baselineAvailable = AvailabilitySnapshot::query()
        ->forProductStore($product->id, $store->id)
        ->where('slot_start', Carbon::parse('2026-08-02T00:00:00Z'))
        ->value('available');

    (new ConfirmVirtualStockIntake)($intake->refresh());

    $demand = Demand::query()
        ->where('source_type', 'virtual_stock_intake')
        ->where('source_id', $intake->id)
        ->first();

    expect($demand)->not->toBeNull()
        ->and($demand->quantity)->toBe(-3)
        ->and($demand->phase)->toBe(DemandPhase::Committed)
        ->and($demand->is_active)->toBeTrue();

    $pipeline->recalculate(
        $product->id,
        $store->id,
        Carbon::parse('2026-08-02T00:00:00Z'),
        Carbon::parse('2026-08-06T00:00:00Z'),
    );

    $insideAvailable = AvailabilitySnapshot::query()
        ->forProductStore($product->id, $store->id)
        ->where('slot_start', Carbon::parse('2026-08-02T00:00:00Z'))
        ->value('available');

    $boundaryAvailable = AvailabilitySnapshot::query()
        ->forProductStore($product->id, $store->id)
        ->where('slot_start', Carbon::parse('2026-08-05T00:00:00Z'))
        ->value('available');

    expect($insideAvailable)->toBe(5)
        ->and($boundaryAvailable)->toBe($baselineAvailable);

    (new ReceiveVirtualStockIntake)($intake->refresh());
    (new ReturnVirtualStockIntake)($intake->refresh());

    $demand->refresh();

    expect($demand->is_active)->toBeFalse()
        ->and($demand->phase)->toBe(DemandPhase::Void);

    $pipeline->recalculate(
        $product->id,
        $store->id,
        Carbon::parse('2026-08-02T00:00:00Z'),
        Carbon::parse('2026-08-06T00:00:00Z'),
    );

    $afterReturnAvailable = AvailabilitySnapshot::query()
        ->forProductStore($product->id, $store->id)
        ->where('slot_start', Carbon::parse('2026-08-02T00:00:00Z'))
        ->value('available');

    expect($afterReturnAvailable)->toBe(2);

    $cancelIntake = createTestVirtualStockIntake([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'quantity' => 2,
        'available_from' => '2026-09-01T00:00:00Z',
        'available_until' => '2026-09-04T00:00:00Z',
    ]);

    (new ConfirmVirtualStockIntake)($cancelIntake);
    $cancelDemand = Demand::query()->where('source_id', $cancelIntake->id)->firstOrFail();
    (new CancelVirtualStockIntake)($cancelIntake->refresh());

    expect($cancelDemand->refresh()->is_active)->toBeFalse();
});
