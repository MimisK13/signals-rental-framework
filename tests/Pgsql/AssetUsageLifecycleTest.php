<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\AllocateAsset;
use App\Actions\Opportunities\ConvertToOrder;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\DispatchAsset;
use App\Actions\Opportunities\DispatchBulkQuantity;
use App\Actions\Opportunities\ReturnAsset;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\AllocateAssetData;
use App\Data\Opportunities\BulkDispatchData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\DispatchAssetData;
use App\Data\Opportunities\ReturnAssetData;
use App\Enums\LineItemTransactionType;
use App\Models\Asset;
use App\Models\AssetUsage;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use App\Models\VirtualStockIntake;
use App\Verbs\Events\Opportunities\AssetSubstituted;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\UsesPostgres;
use Thunk\Verbs\Facades\Verbs;

uses(UsesPostgres::class)->group('pgsql');

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
    $this->store = Store::factory()->create();
    $this->product = Product::factory()->rental()->serialised()->create();
});

function createAssetUsageLine(Store $store, Product $product): OpportunityItem
{
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Asset usage lifecycle',
        'store_id' => $store->id,
        'starts_at' => '2026-10-01T09:00:00Z',
        'ends_at' => '2026-10-05T18:00:00Z',
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

/**
 * @return array{0: Asset, 1: StockLevel}
 */
function createOwnedSerializedStock(Product $product, Store $store): array
{
    $asset = Asset::factory()->create(['product_id' => $product->id]);
    $stockLevel = StockLevel::factory()->serialised()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'asset_id' => $asset->id,
        'is_virtual' => false,
    ]);

    return [$asset, $stockLevel];
}

it('creates one usage from a serialized physical dispatch and preserves its timestamp', function (): void {
    [$asset] = createOwnedSerializedStock($this->product, $this->store);
    $item = createAssetUsageLine($this->store, $this->product);
    $dispatchedAt = '2026-10-01T10:00:00Z';

    (new AllocateAsset)($item, AllocateAssetData::from([
        'stock_level_id' => $asset->currentStockLevel->id,
    ]));
    $assignment = $item->assets()->sole();

    (new DispatchAsset)($assignment, DispatchAssetData::from([
        'dispatched_at' => $dispatchedAt,
    ]));

    $usage = AssetUsage::query()->sole();

    Verbs::replay();
    $usage = AssetUsage::query()->sole();

    expect($usage->asset_id)->toBe($asset->id)
        ->and($usage->opportunity_item_id)->toBe($item->id)
        ->and($usage->dispatched_at->toIso8601String())->toBe(Carbon::parse($dispatchedAt)->toIso8601String())
        ->and($usage->returned_at)->toBeNull();
});

it('closes usage on return and does not change the original timestamp on repeat', function (): void {
    [$asset] = createOwnedSerializedStock($this->product, $this->store);
    $item = createAssetUsageLine($this->store, $this->product);
    $dispatchedAt = '2026-10-01T10:00:00Z';
    $returnedAt = '2026-10-04T16:30:00Z';

    (new AllocateAsset)($item, AllocateAssetData::from(['stock_level_id' => $asset->currentStockLevel->id]));
    $assignment = $item->assets()->sole();
    (new DispatchAsset)($assignment, DispatchAssetData::from(['dispatched_at' => $dispatchedAt]));
    (new ReturnAsset)($assignment->refresh(), ReturnAssetData::from(['returned_at' => $returnedAt]));

    $usage = AssetUsage::query()->sole();
    $originalReturnedAt = $usage->returned_at->toIso8601String();

    Verbs::replay();

    expect(AssetUsage::query()->sole()->returned_at->toIso8601String())->toBe($originalReturnedAt);
});

it('does not create usage for bulk or virtual stock', function (): void {
    $bulkProduct = Product::factory()->rental()->bulk()->create();
    StockLevel::factory()->bulk()->create([
        'product_id' => $bulkProduct->id,
        'store_id' => $this->store->id,
        'quantity_held' => 10,
    ]);
    $bulkItem = createAssetUsageLine($this->store, $bulkProduct);
    (new DispatchBulkQuantity)($bulkItem, BulkDispatchData::from(['quantity' => '1']));

    $intake = VirtualStockIntake::factory()->received()->create([
        'product_id' => $this->product->id,
        'store_id' => $this->store->id,
        'available_from' => '2026-09-01T00:00:00Z',
        'available_until' => '2026-12-01T00:00:00Z',
    ]);
    $virtual = StockLevel::factory()->virtual()->create([
        'product_id' => $this->product->id,
        'store_id' => $this->store->id,
        'virtual_stock_intake_id' => $intake->id,
    ]);
    $virtualItem = createAssetUsageLine($this->store, $this->product);
    (new AllocateAsset)($virtualItem, AllocateAssetData::from(['stock_level_id' => $virtual->id]));
    $virtualAssignment = $virtualItem->assets()->sole();
    (new DispatchAsset)($virtualAssignment, DispatchAssetData::from([
        'dispatched_at' => '2026-10-01T10:00:00Z',
    ]));

    expect(AssetUsage::query()->count())->toBe(0)
        ->and($virtual->asset_id)->toBeNull();
});

it('creates no usage for a pre-dispatch substitution', function (): void {
    [$oldAsset, $oldStock] = createOwnedSerializedStock($this->product, $this->store);
    [$newAsset] = createOwnedSerializedStock($this->product, $this->store);
    $item = createAssetUsageLine($this->store, $this->product);

    (new AllocateAsset)($item, AllocateAssetData::from(['stock_level_id' => $oldStock->id]));
    $assignment = $item->assets()->sole();

    DB::transaction(function () use ($assignment, $newAsset): void {
        AssetSubstituted::fire(
            state_id: $assignment->state_id,
            new_stock_level_id: $newAsset->currentStockLevel->id,
            substituted_at: '2026-10-01T09:30:00Z',
        );
        Verbs::commit();
    });

    expect(AssetUsage::query()->count())->toBe(0);
});

it('closes old usage and opens new usage at the persisted substitution timestamp', function (): void {
    [$oldAsset, $oldStock] = createOwnedSerializedStock($this->product, $this->store);
    [$newAsset] = createOwnedSerializedStock($this->product, $this->store);
    $item = createAssetUsageLine($this->store, $this->product);
    $dispatchedAt = '2026-10-01T10:00:00Z';
    $substitutedAt = '2026-10-01T14:00:00Z';

    (new AllocateAsset)($item, AllocateAssetData::from(['stock_level_id' => $oldStock->id]));
    $assignment = $item->assets()->sole();
    (new DispatchAsset)($assignment, DispatchAssetData::from(['dispatched_at' => $dispatchedAt]));

    DB::transaction(function () use ($assignment, $newAsset, $substitutedAt): void {
        AssetSubstituted::fire(
            state_id: $assignment->state_id,
            new_stock_level_id: $newAsset->currentStockLevel->id,
            substituted_at: $substitutedAt,
        );
        Verbs::commit();
    });

    $usages = AssetUsage::query()->orderBy('asset_id')->get();

    expect($usages)->toHaveCount(2)
        ->and($usages->firstWhere('asset_id', $oldAsset->id)->returned_at->toIso8601String())
        ->toBe(Carbon::parse($substitutedAt)->toIso8601String())
        ->and($usages->firstWhere('asset_id', $newAsset->id)->dispatched_at->toIso8601String())
        ->toBe(Carbon::parse($substitutedAt)->toIso8601String());
});

it('prevents overlapping active usage for one Asset and permits sequential history', function (): void {
    $asset = Asset::factory()->create(['product_id' => $this->product->id]);
    $firstItem = createAssetUsageLine($this->store, $this->product);
    $secondItem = createAssetUsageLine($this->store, $this->product);

    $first = AssetUsage::factory()->create([
        'asset_id' => $asset->id,
        'opportunity_item_id' => $firstItem->id,
        'dispatched_at' => '2026-10-01T10:00:00Z',
        'returned_at' => '2026-10-02T10:00:00Z',
    ]);

    AssetUsage::factory()->create([
        'asset_id' => $asset->id,
        'opportunity_item_id' => $secondItem->id,
        'dispatched_at' => '2026-10-03T10:00:00Z',
    ]);

    expect($first->fresh()->returned_at)->not->toBeNull()
        ->and(AssetUsage::query()->where('asset_id', $asset->id)->count())->toBe(2);

    expect(fn () => AssetUsage::factory()->create([
        'asset_id' => $asset->id,
        'opportunity_item_id' => $firstItem->id,
        'dispatched_at' => '2026-10-04T10:00:00Z',
    ]))->toThrow(QueryException::class);
});

it('restricts deletion of an OpportunityItem with AssetUsage', function (): void {
    $usage = AssetUsage::factory()->create();

    expect(fn () => $usage->opportunityItem->delete())->toThrow(QueryException::class);
});

it('requires both Asset and OpportunityItem foreign keys', function (): void {
    expect(fn () => AssetUsage::factory()->create(['asset_id' => null]))
        ->toThrow(QueryException::class)
        ->and(fn () => AssetUsage::factory()->create(['opportunity_item_id' => null]))
        ->toThrow(QueryException::class);
});
