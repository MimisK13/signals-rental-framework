<?php

use App\Actions\Products\CreateStockLevel;
use App\Data\Products\CreateStockLevelData;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\UsesPostgres;

/*
|--------------------------------------------------------------------------
| Creating stock levels must not poison the transaction on Postgres
|--------------------------------------------------------------------------
|
| Regression (sibling of AddItemDemandAfterCommitPostgresTest #8): the
| StockLevelObserver enqueued the ShouldBeUnique RecalculateAvailabilityJob
| INSIDE the DB::transaction that CreateStockLevel wraps around each create.
| Acquiring the job's unique lock INSERTs into `cache_locks`, which on
| PostgreSQL fails on conflict and aborts the open transaction with
| `SQLSTATE 25P02 current transaction is aborted`. Creating a SECOND stock
| level for the same product/store (bulk serialised entry, demo seeding)
| therefore threw. SQLite tolerated it, which is why the SQLite lane stayed
| green — found live when DemoDataSeeder first seeded serialised assets.
|
| The fix mirrors DemandObserver: defer the dispatch to DB::afterCommit so
| the lock query runs OUTSIDE the transaction.
|
*/

uses(UsesPostgres::class)->group('pgsql');

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actor = User::factory()->owner()->create();
    $this->actingAs($this->actor);
    $this->store = Store::factory()->create(['timezone' => 'UTC']);
});

it('creates multiple serialised stock levels without poisoning the transaction (25P02) on Postgres', function () {
    // Engage the DATABASE cache lock store so the ShouldBeUnique lock acquisition
    // INSERTs into cache_locks, AND the database queue so the first dispatch's
    // job stays queued holding its unique lock (a sync queue would run the job
    // inline and release the lock, hiding the conflict). The second dispatch's
    // lock INSERT then conflicts — the exact statement that aborted the
    // transaction pre-fix.
    //
    // config(['cache.default' => ...]) is NOT enough: UniqueLock resolves the
    // Cache Repository contract, and the underlying `cache.store` singleton was
    // already resolved to phpunit's array store during boot — so the resolved
    // instance itself must be swapped, exactly reproducing a production boot
    // with CACHE_STORE=database.
    config(['cache.default' => 'database', 'queue.default' => 'database']);
    $this->app->instance(
        Repository::class,
        Cache::store('database'),
    );
    $this->app->instance('cache.store', Cache::store('database'));

    // The suite suppresses stock-recalc dispatch globally (phpunit.xml sets
    // AVAILABILITY_SUPPRESS_STOCK_RECALC=true) — re-enable it here, as the
    // observer's dispatch is exactly what this regression exercises.
    config(['availability.suppress_stock_recalc' => false]);

    // track_availability must be on or the observer's pre-check skips the
    // dispatch entirely (the factory's serialised() state defaults it off).
    $product = Product::factory()->rental()->serialised()->create(['track_availability' => true]);

    // Pre-fix the SECOND create threw QueryException(25P02): its unique-lock
    // INSERT conflicted with the row left by the first dispatch and aborted the
    // action's open transaction. Post-fix both complete cleanly.
    foreach (['PG-AST-001', 'PG-AST-002'] as $assetNumber) {
        (new CreateStockLevel)(CreateStockLevelData::from([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
            'item_name' => $product->name,
            'asset_number' => $assetNumber,
            'serial_number' => $assetNumber,
            'barcode' => $assetNumber,
            'quantity_held' => '1',
        ]));
    }

    expect(StockLevel::query()->where('product_id', $product->id)->count())->toBe(2);
});
