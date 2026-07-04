<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\AllocateAsset;
use App\Actions\Opportunities\ConvertToOrder;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\VirtualStock\ConfirmVirtualStockIntake;
use App\Actions\VirtualStock\CreateVirtualStockIntake;
use App\Actions\VirtualStock\ReceiveVirtualStockIntake;
use App\Actions\VirtualStock\ReturnVirtualStockIntake;
use App\Contracts\Availability\AvailabilityResolutionProvider;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\AllocateAssetData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\VirtualStock\CreateVirtualStockIntakeData;
use App\Enums\AvailabilityResolution;
use App\Enums\LineItemTransactionType;
use App\Models\AvailabilitySnapshot;
use App\Models\Demand;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\Sequence;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use App\Models\VirtualStockIntake;
use App\Services\Availability\RecalculationPipeline;
use App\Services\AvailabilityService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\UsesPostgres;

uses(UsesPostgres::class)->group('pgsql');

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
    Carbon::setTestNow(Carbon::parse('2026-07-04T12:00:00Z'));

    $this->app->bind(AvailabilityResolutionProvider::class, fn () => new class implements AvailabilityResolutionProvider
    {
        public function resolve(): AvailabilityResolution
        {
            return AvailabilityResolution::Daily;
        }
    });

    $this->store = Store::factory()->create(['timezone' => 'UTC']);
    $this->product = Product::factory()->rental()->serialised()->create();
});

afterEach(function () {
    Carbon::setTestNow();
});

function syncReplaySequences(): void
{
    Sequence::query()->updateOrCreate(
        ['name' => 'stock_levels'],
        ['next_value' => (int) StockLevel::query()->max('id') + 1],
    );

    Sequence::query()->updateOrCreate(
        ['name' => 'demands'],
        ['next_value' => (int) Demand::query()->max('id') + 1],
    );
}

function createPgSerialisedIntake(Store $store, Product $product, int $quantity = 2): VirtualStockIntake
{
    $result = (new CreateVirtualStockIntake)(CreateVirtualStockIntakeData::from([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'quantity' => $quantity,
        'available_from' => '2026-08-01T00:00:00Z',
        'available_until' => '2026-08-06T00:00:00Z',
        'unit_cost' => 1200,
    ]));

    return VirtualStockIntake::query()->findOrFail($result->id);
}

function pgSlotSnapshot(int $productId, int $storeId, Carbon $from, Carbon $to): ?AvailabilitySnapshot
{
    return AvailabilitySnapshot::query()
        ->forProductStore($productId, $storeId)
        ->where('slot_start', '>=', $from)
        ->where('slot_start', '<', $to)
        ->orderBy('slot_start')
        ->first();
}

it('creates serialised virtual assets with correct supply windows on postgres', function () {
    $intake = createPgSerialisedIntake($this->store, $this->product, 3);
    app(ConfirmVirtualStockIntake::class)($intake->refresh());

    $assets = StockLevel::query()
        ->where('virtual_stock_intake_id', $intake->id)
        ->orderBy('asset_number')
        ->get();

    expect($assets)->toHaveCount(3)
        ->and($assets->first()?->starts_at?->utc()->format('Y-m-d\TH:i:s\Z'))->toBe('2026-08-01T00:00:00Z')
        ->and($assets->first()?->ends_at?->utc()->format('Y-m-d\TH:i:s\Z'))->toBe('2026-08-06T00:00:00Z');

    $supplyDemands = Demand::query()
        ->where('source_type', 'virtual_stock_intake')
        ->where('source_id', $intake->id)
        ->whereNotNull('asset_id')
        ->get();

    expect($supplyDemands)->toHaveCount(3)
        ->and($supplyDemands->every(fn (Demand $demand): bool => $demand->quantity === -1))->toBeTrue()
        ->and(Demand::query()->where('source_id', $intake->id)->whereNull('asset_id')->exists())->toBeFalse();

    $period = DB::connection('pgsql_testing')->selectOne(
        'SELECT period::text AS period FROM demands WHERE id = ?',
        [$supplyDemands->first()->id],
    );

    expect($period?->period)->not->toBeNull()
        ->and($period?->period)->toContain('2026-08-01');
});

it('does not double count serialised virtual supply when an asset is allocated', function () {
    StockLevel::factory()->serialised()->create([
        'product_id' => $this->product->id,
        'store_id' => $this->store->id,
    ]);
    syncReplaySequences();

    $intake = createPgSerialisedIntake($this->store, $this->product, 1);
    $service = app(AvailabilityService::class);
    $pipeline = app(RecalculationPipeline::class);
    $pointDate = Carbon::parse('2026-08-02T12:00:00Z');
    $from = Carbon::parse('2026-08-02T00:00:00Z');
    $to = Carbon::parse('2026-08-06T00:00:00Z');

    $beforeConfirm = $service->getAvailability($this->product->id, $this->store->id, $pointDate)->available;

    app(ConfirmVirtualStockIntake::class)($intake->refresh());

    $afterConfirmPoint = $service->getAvailability($this->product->id, $this->store->id, $pointDate);

    expect($afterConfirmPoint->available)->toBe($beforeConfirm + 1);

    $pipeline->recalculate($this->product->id, $this->store->id, $from, $to);
    $snapshot = pgSlotSnapshot($this->product->id, $this->store->id, $from, $to);

    expect($snapshot?->available)->toBe($afterConfirmPoint->available);

    $virtualAsset = StockLevel::query()->where('virtual_stock_intake_id', $intake->id)->firstOrFail();

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'PG virtual asset allocation',
        'store_id' => $this->store->id,
        'starts_at' => '2026-08-02T09:00:00Z',
        'ends_at' => '2026-08-05T17:00:00Z',
    ]));
    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();
    (new ConvertToQuotation)($opportunity);
    (new AddOpportunityItem)($opportunity->refresh(), AddOpportunityItemData::from([
        'name' => $this->product->name,
        'itemable_id' => $this->product->id,
        'itemable_type' => Product::class,
        'quantity' => '1',
        'transaction_type' => LineItemTransactionType::Rental->value,
    ]));
    (new ConvertToOrder)($opportunity->refresh());
    $item = $opportunity->items()->firstOrFail();

    (new AllocateAsset)($item, AllocateAssetData::from(['stock_level_id' => $virtualAsset->id]));

    $afterAllocation = $service->getAvailability($this->product->id, $this->store->id, $pointDate)->available;

    expect($afterAllocation)->toBe($beforeConfirm)
        ->and(Demand::query()->where('source_id', $intake->id)->whereNull('asset_id')->exists())->toBeFalse()
        ->and(Demand::query()->where('asset_id', $virtualAsset->id)->where('is_active', true)->count())->toBe(2);
});

it('retires serialised virtual supply on return', function () {
    $intake = createPgSerialisedIntake($this->store, $this->product, 2);
    $service = app(AvailabilityService::class);
    $pipeline = app(RecalculationPipeline::class);
    $pointDate = Carbon::parse('2026-08-02T12:00:00Z');
    $from = Carbon::parse('2026-08-02T00:00:00Z');
    $to = Carbon::parse('2026-08-06T00:00:00Z');

    app(ConfirmVirtualStockIntake::class)($intake->refresh());
    $pipeline->recalculate($this->product->id, $this->store->id, $from, $to);

    $withSupply = $service->getAvailability($this->product->id, $this->store->id, $pointDate)->available;

    app(ReceiveVirtualStockIntake::class)($intake->refresh());
    app(ReturnVirtualStockIntake::class)($intake->refresh());
    $pipeline->recalculate($this->product->id, $this->store->id, $from, $to);

    $afterReturn = $service->getAvailability($this->product->id, $this->store->id, $pointDate)->available;

    expect($withSupply)->toBe(2)
        ->and($afterReturn)->toBe(0)
        ->and(StockLevel::query()->where('virtual_stock_intake_id', $intake->id)->value('virtual_asset_status'))->toBe('retired');
});
