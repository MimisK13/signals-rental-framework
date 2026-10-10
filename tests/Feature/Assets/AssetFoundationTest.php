<?php

use App\Actions\Products\CreateStockLevel;
use App\Actions\Products\MergeProduct;
use App\Actions\Products\UpdateStockLevel;
use App\Data\Products\CreateStockLevelData;
use App\Data\Products\MergeProductData;
use App\Data\Products\UpdateStockLevelData;
use App\Enums\AssetExpenseType;
use App\Enums\AssetOwnershipType;
use App\Enums\StockCategory;
use App\Events\AuditableEvent;
use App\Models\Asset;
use App\Models\AssetExpense;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

it('keeps Asset identity independent from the current StockLevel', function (): void {
    $asset = Asset::factory()->create();

    expect($asset->currentStockLevel)->toBeNull();
});

it('links one StockLevel to one Asset and prevents a second current link', function (): void {
    $asset = Asset::factory()->create();
    $product = $asset->product;
    $first = StockLevel::factory()->serialised()->create([
        'product_id' => $product->id,
        'asset_id' => $asset->id,
    ]);

    expect($asset->fresh()->currentStockLevel->is($first))->toBeTrue()
        ->and($first->fresh()->asset->is($asset))->toBeTrue();

    expect(fn () => StockLevel::factory()->serialised()->create([
        'product_id' => $product->id,
        'asset_id' => $asset->id,
    ]))->toThrow(QueryException::class);
});

it('leaves Asset financial identity when its StockLevel is deleted', function (): void {
    $asset = Asset::factory()->create();
    $stockLevel = StockLevel::factory()->serialised()->create([
        'product_id' => $asset->product_id,
        'asset_id' => $asset->id,
    ]);

    $stockLevel->delete();

    expect(Asset::find($asset->id))->not->toBeNull()
        ->and(Asset::find($asset->id)->currentStockLevel)->toBeNull();
});

it('does not duplicate StockLevel identifiers on Asset', function (): void {
    expect(Schema::hasColumn('assets', 'asset_number'))->toBeFalse()
        ->and(Schema::hasColumn('assets', 'serial_number'))->toBeFalse()
        ->and(Schema::hasColumn('assets', 'barcode'))->toBeFalse();
});

it('automatically creates an unknown Asset for new serialized non-virtual stock', function (): void {
    Event::fake([AuditableEvent::class]);

    $product = Product::factory()->serialised()->create();
    $result = (new CreateStockLevel)(CreateStockLevelData::from([
        'product_id' => $product->id,
        'store_id' => Store::factory()->create()->id,
        'asset_number' => 'A-FOUNDATION-001',
        'serial_number' => 'SN-FOUNDATION-001',
        'barcode' => 'BC-FOUNDATION-001',
        'quantity_held' => '1',
    ]));

    $stockLevel = StockLevel::findOrFail($result->id);
    $asset = $stockLevel->asset;

    expect($asset)->not->toBeNull()
        ->and($asset->product_id)->toBe($product->id)
        ->and($asset->ownership_type)->toBe(AssetOwnershipType::Unknown)
        ->and($asset->acquisition_amount)->toBeNull()
        ->and($asset->acquired_at)->toBeNull()
        ->and($stockLevel->asset_number)->toBe('A-FOUNDATION-001')
        ->and($stockLevel->serial_number)->toBe('SN-FOUNDATION-001')
        ->and($stockLevel->barcode)->toBe('BC-FOUNDATION-001');
});

it('does not automatically create an Asset for bulk stock', function (): void {
    Event::fake([AuditableEvent::class]);

    $product = Product::factory()->bulk()->create();
    $result = (new CreateStockLevel)(CreateStockLevelData::from([
        'product_id' => $product->id,
        'store_id' => Store::factory()->create()->id,
        'quantity_held' => '20',
    ]));

    expect(StockLevel::findOrFail($result->id)->asset_id)->toBeNull()
        ->and(Asset::query()->where('product_id', $product->id)->exists())->toBeFalse();
});

it('rejects serialized stock quantities other than one', function (): void {
    $product = Product::factory()->serialised()->create();

    expect(fn () => (new CreateStockLevel)(CreateStockLevelData::from([
        'product_id' => $product->id,
        'store_id' => Store::factory()->create()->id,
        'quantity_held' => '2',
    ])))->toThrow(ValidationException::class, 'exactly 1');

    expect(StockLevel::query()->where('product_id', $product->id)->exists())->toBeFalse()
        ->and(Asset::query()->where('product_id', $product->id)->exists())->toBeFalse();
});

it('does not create an Asset for virtual stock', function (): void {
    $product = Product::factory()->serialised()->create();
    $stockLevel = StockLevel::factory()->virtual()->create([
        'product_id' => $product->id,
    ]);

    expect($stockLevel->asset_id)->toBeNull()
        ->and(Asset::query()->where('product_id', $product->id)->exists())->toBeFalse();
});

it('rejects changing Product on a StockLevel linked to an Asset', function (): void {
    $stockLevel = StockLevel::factory()->serialised()->create([
        'product_id' => Product::factory()->serialised(),
    ]);
    $asset = Asset::factory()->create(['product_id' => $stockLevel->product_id]);
    $stockLevel->update(['asset_id' => $asset->id]);
    $newProduct = Product::factory()->serialised()->create();

    expect(fn () => (new UpdateStockLevel)($stockLevel, UpdateStockLevelData::from([
        'product_id' => $newProduct->id,
    ])))->toThrow(ValidationException::class, 'cannot change Product');
});

it('retains Product-change behaviour for an unlinked StockLevel', function (): void {
    $oldProduct = Product::factory()->bulk()->create();
    $newProduct = Product::factory()->bulk()->create();
    $stockLevel = StockLevel::factory()->bulk()->create(['product_id' => $oldProduct->id]);

    (new UpdateStockLevel)($stockLevel, UpdateStockLevelData::from([
        'product_id' => $newProduct->id,
    ]));

    expect($stockLevel->refresh()->product_id)->toBe($newProduct->id);
});

it('rejects converting a linked Asset StockLevel to bulk or virtual', function (): void {
    $stockLevel = StockLevel::factory()->serialised()->create([
        'product_id' => Product::factory()->serialised(),
    ]);
    $asset = Asset::factory()->create(['product_id' => $stockLevel->product_id]);
    $stockLevel->update(['asset_id' => $asset->id]);

    expect(fn () => (new UpdateStockLevel)($stockLevel, UpdateStockLevelData::from([
        'stock_category' => StockCategory::BulkStock->value,
    ])))->toThrow(ValidationException::class, 'must remain serialised');

    expect(fn () => (new UpdateStockLevel)($stockLevel, UpdateStockLevelData::from([
        'is_virtual' => true,
    ])))->toThrow(ValidationException::class, 'cannot become virtual');
});

it('updates related Asset Product identity during canonical Product merge', function (): void {
    Event::fake([AuditableEvent::class]);

    $primary = Product::factory()->serialised()->create();
    $secondary = Product::factory()->serialised()->create();
    $stockLevel = StockLevel::factory()->serialised()->create(['product_id' => $secondary->id]);
    $asset = Asset::factory()->create(['product_id' => $secondary->id]);
    $assetId = $asset->id;
    $stockLevel->update(['asset_id' => $asset->id]);

    (new MergeProduct)(MergeProductData::from([
        'primary_id' => $primary->id,
        'secondary_id' => $secondary->id,
    ]));

    expect($stockLevel->refresh()->product_id)->toBe($primary->id)
        ->and($asset->refresh()->product_id)->toBe($primary->id)
        ->and($asset->id)->toBe($assetId);
});

it('casts AssetExpense type and preserves minor-unit money fields', function (): void {
    $supplier = User::factory()->owner()->create()->member;
    $expense = AssetExpense::factory()->create([
        'expense_type' => AssetExpenseType::Repair,
        'supplier_member_id' => $supplier->id,
        'amount' => 18000,
        'currency_code' => 'EUR',
        'exchange_rate' => '0.86420000',
        'base_currency_code' => 'GBP',
        'base_amount' => 15556,
    ]);

    expect($expense->expense_type)->toBe(AssetExpenseType::Repair)
        ->and($expense->amount)->toBe(18000)
        ->and($expense->base_amount)->toBe(15556)
        ->and($expense->asset)->toBeInstanceOf(Asset::class)
        ->and($expense->supplier->is($supplier))->toBeTrue();
});

it('backfills valid serialized non-virtual StockLevels idempotently without purchase price', function (): void {
    $product = Product::factory()->serialised()->create(['purchase_price' => 999999]);
    $valid = StockLevel::factory()->serialised()->create([
        'product_id' => $product->id,
        'quantity_held' => 1,
        'asset_id' => null,
    ]);
    $bulk = StockLevel::factory()->bulk()->create(['product_id' => Product::factory()->bulk()]);
    $virtual = StockLevel::factory()->virtual()->create(['product_id' => $product->id]);
    $alreadyLinked = Asset::factory()->create(['product_id' => $product->id]);
    StockLevel::factory()->serialised()->create([
        'product_id' => $product->id,
        'asset_id' => $alreadyLinked->id,
    ]);
    $invalid = StockLevel::factory()->serialised()->create([
        'product_id' => $product->id,
        'quantity_held' => 2,
        'asset_id' => null,
    ]);

    $this->artisan('assets:backfill-from-stock-levels')->assertExitCode(0);

    $valid->refresh();
    $createdAsset = $valid->asset;

    expect($createdAsset)->not->toBeNull()
        ->and($createdAsset->product_id)->toBe($product->id)
        ->and($createdAsset->ownership_type)->toBe(AssetOwnershipType::Unknown)
        ->and($createdAsset->acquisition_amount)->toBeNull()
        ->and($bulk->refresh()->asset_id)->toBeNull()
        ->and($virtual->refresh()->asset_id)->toBeNull()
        ->and($invalid->refresh()->asset_id)->toBeNull();

    $assetCount = Asset::query()->count();

    $this->artisan('assets:backfill-from-stock-levels')->assertExitCode(0);

    expect(Asset::query()->count())->toBe($assetCount);
});
