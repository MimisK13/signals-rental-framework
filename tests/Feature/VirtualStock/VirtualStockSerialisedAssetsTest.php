<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\AllocateAsset;
use App\Actions\Opportunities\ConvertToOrder;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\VirtualStock\CancelVirtualStockIntake;
use App\Actions\VirtualStock\ConfirmVirtualStockIntake;
use App\Actions\VirtualStock\CreateVirtualStockIntake;
use App\Actions\VirtualStock\DeleteDraftVirtualStockIntake;
use App\Actions\VirtualStock\ReceiveVirtualStockIntake;
use App\Actions\VirtualStock\ReturnVirtualStockIntake;
use App\Contracts\Availability\AvailabilityResolutionProvider;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\AllocateAssetData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\VirtualStock\CreateVirtualStockIntakeData;
use App\Enums\AvailabilityResolution;
use App\Enums\LineItemTransactionType;
use App\Models\Demand;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\OpportunityItemAsset;
use App\Models\Product;
use App\Models\Sequence;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use App\Models\VirtualStockIntake;
use App\Services\Availability\OpportunityItemDemandResolver;
use App\Services\Availability\RecalculationPipeline;
use App\Services\AvailabilityService;
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

/**
 * @param  array<string, mixed>  $overrides
 */
function createSerialisedVirtualStockIntake(array $overrides = []): VirtualStockIntake
{
    $store = Store::factory()->create(['timezone' => 'UTC']);
    $product = Product::factory()->rental()->serialised()->create();

    $result = (new CreateVirtualStockIntake)(CreateVirtualStockIntakeData::from([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'quantity' => 2,
        'available_from' => '2026-08-01T00:00:00Z',
        'available_until' => '2026-08-06T00:00:00Z',
        'unit_cost' => 1500,
        ...$overrides,
    ]));

    return VirtualStockIntake::query()->findOrFail($result->id);
}

function makeOrderLineForProduct(Store $store, Product $product): OpportunityItem
{
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Serialised virtual stock allocation',
        'store_id' => $store->id,
        'starts_at' => '2026-08-02T09:00:00Z',
        'ends_at' => '2026-08-05T17:00:00Z',
    ]));
    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();
    (new ConvertToQuotation)($opportunity);
    (new AddOpportunityItem)($opportunity->refresh(), AddOpportunityItemData::from([
        'name' => $product->name,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'quantity' => '1',
        'transaction_type' => LineItemTransactionType::Rental->value,
    ]));
    (new ConvertToOrder)($opportunity->refresh());

    return $opportunity->items()->firstOrFail();
}

it('creates VS-numbered virtual assets on confirm for serialised intakes', function () {
    $intake = createSerialisedVirtualStockIntake();
    $eventsBefore = verbEventCount();

    app(ConfirmVirtualStockIntake::class)($intake->refresh());

    expect(verbEventCount() - $eventsBefore)->toBe(2);

    $assets = StockLevel::query()
        ->where('virtual_stock_intake_id', $intake->id)
        ->where('is_virtual', true)
        ->orderBy('asset_number')
        ->get();

    expect($assets)->toHaveCount(2)
        ->and($assets[0]->asset_number)->toBe($intake->number.'-01')
        ->and($assets[1]->asset_number)->toBe($intake->number.'-02')
        ->and($assets[0]->starts_at?->utc()->format('Y-m-d\TH:i:s\Z'))->toBe('2026-08-01T00:00:00Z')
        ->and($assets[0]->ends_at?->utc()->format('Y-m-d\TH:i:s\Z'))->toBe('2026-08-06T00:00:00Z')
        ->and($assets[0]->virtual_asset_status)->toBe('available');

    $aggregateDemand = Demand::query()
        ->where('source_type', 'virtual_stock_intake')
        ->where('source_id', $intake->id)
        ->whereNull('asset_id')
        ->first();

    expect($aggregateDemand)->toBeNull();

    $supplyDemands = Demand::query()
        ->where('source_type', 'virtual_stock_intake')
        ->where('source_id', $intake->id)
        ->whereNotNull('asset_id')
        ->get();

    expect($supplyDemands)->toHaveCount(2)
        ->and($supplyDemands->every(fn (Demand $demand): bool => $demand->quantity === -1))->toBeTrue();
});

it('does not create virtual assets for draft intakes or bulk products', function () {
    $draft = createSerialisedVirtualStockIntake();
    expect(StockLevel::query()->where('virtual_stock_intake_id', $draft->id)->count())->toBe(0);

    (new DeleteDraftVirtualStockIntake)($draft);
    expect(StockLevel::query()->where('virtual_stock_intake_id', $draft->id)->count())->toBe(0);

    $store = Store::factory()->create(['timezone' => 'UTC']);
    $bulkProduct = Product::factory()->bulk()->create();
    $bulkIntake = createSerialisedVirtualStockIntake([
        'product_id' => $bulkProduct->id,
        'store_id' => $store->id,
        'quantity' => 3,
    ]);

    app(ConfirmVirtualStockIntake::class)($bulkIntake->refresh());

    expect(StockLevel::query()->where('virtual_stock_intake_id', $bulkIntake->id)->count())->toBe(0)
        ->and(Demand::query()->where('source_id', $bulkIntake->id)->whereNull('asset_id')->value('quantity'))->toBe(-3);
});

it('allocates a virtual asset through the standard per-asset path', function () {
    $intake = createSerialisedVirtualStockIntake();
    app(ConfirmVirtualStockIntake::class)($intake->refresh());

    $asset = StockLevel::query()->where('virtual_stock_intake_id', $intake->id)->orderBy('id')->firstOrFail();
    $intake->load('product', 'store');
    $item = makeOrderLineForProduct($intake->store, $intake->product);

    (new AllocateAsset)($item, AllocateAssetData::from(['stock_level_id' => $asset->id]));

    expect(OpportunityItemAsset::query()->where('stock_level_id', $asset->id)->count())->toBe(1)
        ->and($asset->refresh()->virtual_asset_status)->toBe('allocated')
        ->and(Demand::query()->where('asset_id', $asset->id)->where('quantity', 1)->where('is_active', true)->count())->toBe(1);
});

it('reconstructs serialised virtual stock projections byte-identically after replay', function () {
    $intake = createSerialisedVirtualStockIntake();
    app(ConfirmVirtualStockIntake::class)($intake->refresh());

    $asset = StockLevel::query()->where('virtual_stock_intake_id', $intake->id)->orderBy('id')->firstOrFail();
    $intake->load('product', 'store');
    $item = makeOrderLineForProduct($intake->store, $intake->product);
    (new AllocateAsset)($item, AllocateAssetData::from(['stock_level_id' => $asset->id]));

    app(ReceiveVirtualStockIntake::class)($intake->refresh());
    app(ReturnVirtualStockIntake::class)($intake->refresh());

    $intakesBefore = VirtualStockIntake::withTrashed()->orderBy('id')->get()->map(
        fn (VirtualStockIntake $row): array => $row->getAttributes(),
    )->all();

    $assetsBefore = StockLevel::query()->orderBy('id')->get()->map(
        fn (StockLevel $row): array => $row->getAttributes(),
    )->all();

    $virtualSupplyDemandsBefore = Demand::query()
        ->where('source_type', 'virtual_stock_intake')
        ->where('source_id', $intake->id)
        ->orderBy('id')
        ->get()
        ->map(fn (Demand $row): array => $row->getAttributes())
        ->all();

    $allocationDemandBefore = Demand::query()
        ->where('source_type', 'opportunity_item')
        ->where('source_id', $item->id)
        ->where('asset_id', $asset->id)
        ->firstOrFail()
        ->getAttributes();

    $assignmentsBefore = OpportunityItemAsset::query()->orderBy('id')->get()->map(
        fn (OpportunityItemAsset $row): array => $row->getAttributes(),
    )->all();

    $eventCountBeforeReplay = verbEventCount();

    OpportunityItemAsset::query()->delete();
    StockLevel::query()->update(['quantity_allocated' => 0]);
    StockLevel::query()->where('is_virtual', true)->delete();
    VirtualStockIntake::withTrashed()->forceDelete();
    Sequence::query()->delete();

    Verbs::replay();

    app(OpportunityItemDemandResolver::class)->syncDemands($item->refresh());

    expect(verbEventCount())->toBe($eventCountBeforeReplay);

    $allocationDemandAfter = Demand::query()
        ->where('source_type', 'opportunity_item')
        ->where('source_id', $item->id)
        ->where('asset_id', $asset->id)
        ->firstOrFail()
        ->getAttributes();

    expect(VirtualStockIntake::withTrashed()->orderBy('id')->get()->map(
        fn (VirtualStockIntake $row): array => $row->getAttributes(),
    )->all())->toBe($intakesBefore)
        ->and(StockLevel::query()->orderBy('id')->get()->map(
            fn (StockLevel $row): array => $row->getAttributes(),
        )->all())->toBe($assetsBefore)
        ->and(Demand::query()
            ->where('source_type', 'virtual_stock_intake')
            ->where('source_id', $intake->id)
            ->orderBy('id')
            ->get()
            ->map(fn (Demand $row): array => $row->getAttributes())
            ->all())->toBe($virtualSupplyDemandsBefore)
        ->and(collect($allocationDemandAfter)->except('id', 'created_at', 'updated_at')->all())
        ->toBe(collect($allocationDemandBefore)->except('id', 'created_at', 'updated_at')->all())
        ->and(OpportunityItemAsset::query()->orderBy('id')->get()->map(
            fn (OpportunityItemAsset $row): array => $row->getAttributes(),
        )->all())->toBe($assignmentsBefore);
});

it('retires virtual assets and voids supply on return and cancel', function () {
    $intake = createSerialisedVirtualStockIntake();
    app(ConfirmVirtualStockIntake::class)($intake->refresh());
    app(ReceiveVirtualStockIntake::class)($intake->refresh());
    app(ReturnVirtualStockIntake::class)($intake->refresh());

    $assets = StockLevel::query()->where('virtual_stock_intake_id', $intake->id)->get();
    expect($assets->every(fn (StockLevel $asset): bool => $asset->virtual_asset_status === 'retired'))->toBeTrue()
        ->and(Demand::query()->where('source_id', $intake->id)->where('is_active', true)->count())->toBe(0);

    $cancelIntake = createSerialisedVirtualStockIntake();
    app(ConfirmVirtualStockIntake::class)($cancelIntake->refresh());
    app(CancelVirtualStockIntake::class)($cancelIntake->refresh());

    expect(StockLevel::query()->where('virtual_stock_intake_id', $cancelIntake->id)->value('virtual_asset_status'))->toBe('retired');
});

it('increases availability via per-asset supply without an aggregate demand row', function () {
    $intake = createSerialisedVirtualStockIntake(['quantity' => 2]);
    $intake->load('product', 'store');
    $product = $intake->product;
    $store = $intake->store;

    $pipeline = app(RecalculationPipeline::class);
    $pipeline->recalculate(
        $product->id,
        $store->id,
        Carbon::parse('2026-08-02T00:00:00Z'),
        Carbon::parse('2026-08-06T00:00:00Z'),
    );

    $baseline = app(AvailabilityService::class)
        ->getAvailability($product->id, $store->id, Carbon::parse('2026-08-02T00:00:00Z'))
        ->available;

    app(ConfirmVirtualStockIntake::class)($intake->refresh());

    $pipeline->recalculate(
        $product->id,
        $store->id,
        Carbon::parse('2026-08-02T00:00:00Z'),
        Carbon::parse('2026-08-06T00:00:00Z'),
    );

    $afterConfirm = app(AvailabilityService::class)
        ->getAvailability($product->id, $store->id, Carbon::parse('2026-08-02T00:00:00Z'))
        ->available;

    expect($afterConfirm)->toBe($baseline + 2)
        ->and(Demand::query()->where('source_id', $intake->id)->whereNull('asset_id')->exists())->toBeFalse();
});

it('exposes virtual assets on the API show include', function () {
    $intake = createSerialisedVirtualStockIntake();
    app(ConfirmVirtualStockIntake::class)($intake->refresh());

    $response = $this->getJson("/api/v1/virtual_stock_intakes/{$intake->id}?include=virtualAssets")
        ->assertSuccessful();

    expect($response->json('virtual_stock_intake.virtual_assets'))->toHaveCount(2)
        ->and($response->json('virtual_stock_intake.virtual_assets.0.asset_number'))->toEndWith('-01');
});
