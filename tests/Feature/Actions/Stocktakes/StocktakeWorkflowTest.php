<?php

use App\Actions\Products\AdjustStockLevel;
use App\Actions\Stocktakes\CancelStocktake;
use App\Actions\Stocktakes\CompleteStocktake;
use App\Actions\Stocktakes\CreateStocktake;
use App\Actions\Stocktakes\RecordBulkStocktakeObservation;
use App\Actions\Stocktakes\StartStocktake;
use App\Data\Products\AdjustStockLevelData;
use App\Data\Stocktakes\CreateStocktakeData;
use App\Data\Stocktakes\RecordBulkStocktakeObservationData;
use App\Enums\StocktakeStatus;
use App\Enums\TransactionType;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StocktakeObservation;
use App\Models\StockTransaction;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->user = User::factory()->owner()->create();
    $this->actingAs($this->user);
    $this->store = Store::factory()->create();
    $this->product = Product::factory()->bulk()->create(['name' => 'XLR Cable 10m']);
});

/** @param array<string, mixed> $overrides */
function createBulkStockLevelForStocktake(int $storeId, int $productId, array $overrides = []): StockLevel
{
    return StockLevel::factory()->bulk()->create([
        'store_id' => $storeId,
        'product_id' => $productId,
        'quantity_held' => 100,
        ...$overrides,
    ]);
}

it('creates and starts a bulk stocktake with a stable quantity snapshot', function (): void {
    $level = createBulkStockLevelForStocktake($this->store->id, $this->product->id);

    $stocktake = (new CreateStocktake)(CreateStocktakeData::from(['store_id' => $this->store->id]));
    expect($stocktake->status)->toBe(StocktakeStatus::Draft);

    $started = (new StartStocktake)($stocktake);
    $line = $started->lines()->firstOrFail();

    expect($started->status)->toBe(StocktakeStatus::InProgress)
        ->and($started->started_by_user_id)->toBe($this->user->id)
        ->and((float) $line->quantity_held_snapshot)->toBe(100.0)
        ->and($line->stock_level_id)->toBe($level->id);
});

it('excludes virtual, serialized, asset-linked, and container-associated levels', function (): void {
    $eligible = createBulkStockLevelForStocktake($this->store->id, $this->product->id);
    StockLevel::factory()->bulk()->create(['store_id' => $this->store->id, 'product_id' => $this->product->id, 'is_virtual' => true]);
    StockLevel::factory()->serialised()->create(['store_id' => $this->store->id]);
    StockLevel::factory()->bulk()->create(['store_id' => $this->store->id, 'product_id' => $this->product->id, 'container_stock_level_id' => $eligible->id]);

    $stocktake = (new StartStocktake)(
        (new CreateStocktake)(CreateStocktakeData::from(['store_id' => $this->store->id])),
    );

    expect($stocktake->lines)->toHaveCount(1)
        ->and($stocktake->lines->first()->stock_level_id)->toBe($eligible->id);
});

it('records zero, calculates delta, and appends recount evidence', function (): void {
    createBulkStockLevelForStocktake($this->store->id, $this->product->id);
    $stocktake = (new StartStocktake)((new CreateStocktake)(CreateStocktakeData::from(['store_id' => $this->store->id])));
    $line = $stocktake->lines->firstOrFail();

    $first = (new RecordBulkStocktakeObservation)($line, RecordBulkStocktakeObservationData::from(['counted_quantity' => '0']));
    $second = (new RecordBulkStocktakeObservation)($line->fresh(), RecordBulkStocktakeObservationData::from(['counted_quantity' => '97', 'notes' => 'Recount']));
    $line = $line->fresh(['acceptedObservation', 'observations']);

    expect($first->counted_quantity)->toBe('0.00')
        ->and($second->counted_quantity)->toBe('97.00')
        ->and($line->accepted_observation_id)->toBe($second->id)
        ->and($line->observations)->toHaveCount(2)
        ->and($line->observationDelta())->toBe('-3.00');
});

it('keeps the snapshot unchanged when D2 changes current quantity', function (): void {
    $level = createBulkStockLevelForStocktake($this->store->id, $this->product->id);
    $stocktake = (new StartStocktake)((new CreateStocktake)(CreateStocktakeData::from(['store_id' => $this->store->id])));

    (new AdjustStockLevel)($level, AdjustStockLevelData::from([
        'transaction_type' => TransactionType::WriteOff->value,
        'quantity' => '3',
        'reason' => 'Known damaged cables',
    ]));

    $line = $stocktake->lines()->firstOrFail();
    (new RecordBulkStocktakeObservation)($line, RecordBulkStocktakeObservationData::from(['counted_quantity' => '97']));

    expect((float) $level->fresh()->quantity_held)->toBe(97.0)
        ->and((float) $line->fresh()->quantity_held_snapshot)->toBe(100.0)
        ->and(StockTransaction::query()->count())->toBe(1);
});

it('completes and cancels only from in progress and retains evidence', function (): void {
    createBulkStockLevelForStocktake($this->store->id, $this->product->id);
    $stocktake = (new StartStocktake)((new CreateStocktake)(CreateStocktakeData::from(['store_id' => $this->store->id])));
    $line = $stocktake->lines->firstOrFail();
    (new RecordBulkStocktakeObservation)($line, RecordBulkStocktakeObservationData::from(['counted_quantity' => '100']));

    $completed = (new CompleteStocktake)($stocktake);
    expect($completed->status)->toBe(StocktakeStatus::Completed)
        ->and(StocktakeObservation::query()->count())->toBe(1);

    expect(fn () => (new RecordBulkStocktakeObservation)($line, RecordBulkStocktakeObservationData::from(['counted_quantity' => '99'])))
        ->toThrow(ValidationException::class);

    expect(fn () => $completed->update(['notes' => 'changed']))
        ->toThrow(LogicException::class)
        ->and(fn () => $line->update(['quantity_held_snapshot' => '99']))
        ->toThrow(LogicException::class)
        ->and(fn () => $completed->delete())
        ->toThrow(LogicException::class);

    $cancelled = (new CancelStocktake)(
        (new StartStocktake)((new CreateStocktake)(CreateStocktakeData::from(['store_id' => $this->store->id]))),
    );
    expect($cancelled->status)->toBe(StocktakeStatus::Cancelled);
});

it('requires stock.count and Store access', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    expect(fn () => (new CreateStocktake)(CreateStocktakeData::from(['store_id' => $this->store->id])))
        ->toThrow(AuthorizationException::class);

    $user->givePermissionTo('stock.count');

    expect(fn () => (new CreateStocktake)(CreateStocktakeData::from(['store_id' => $this->store->id])))
        ->toThrow(AuthorizationException::class);
});
