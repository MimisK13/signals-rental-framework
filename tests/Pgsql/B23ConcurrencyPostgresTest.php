<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\AllocateAsset;
use App\Actions\Opportunities\ChangeItemReservation;
use App\Actions\Opportunities\ConvertToOrder;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\VirtualStock\ConfirmVirtualStockIntake;
use App\Actions\VirtualStock\CreateVirtualStockIntake;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\AllocateAssetData;
use App\Data\Opportunities\ChangeItemReservationData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\VirtualStock\CreateVirtualStockIntakeData;
use App\Enums\AssetLifecycleStatus;
use App\Enums\LineItemTransactionType;
use App\Enums\OpportunityItemType;
use App\Enums\OpportunityStatus;
use App\Models\Asset;
use App\Models\AssetLifecycleEvent;
use App\Models\Demand;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\OpportunityItemAsset;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use App\Models\VirtualStockIntake;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;
use Tests\Concerns\UsesPostgres;

uses(UsesPostgres::class)->group('pgsql');

beforeEach(function (): void {
    $this->store = Store::factory()->create(['timezone' => 'UTC']);
    $this->product = Product::factory()->rental()->bulk()->create();
    StockLevel::factory()->bulk()->create([
        'product_id' => $this->product->id,
        'store_id' => $this->store->id,
        'quantity_held' => 10,
    ]);
    $this->actingAs(User::factory()->owner()->create());
});

function makeConcurrencyItem(Store $store, Product $product, string $subject): OpportunityItem
{
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => $subject,
        'store_id' => $store->id,
        'starts_at' => '2026-11-01T09:00:00Z',
        'ends_at' => '2026-11-03T17:00:00Z',
    ]));
    $opportunity = Opportunity::query()->findOrFail($created->id);

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => $product->name,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'item_type' => OpportunityItemType::Product->value,
        'quantity' => '10',
        'transaction_type' => LineItemTransactionType::Rental->value,
    ]));

    $item = $opportunity->refresh()->items()->firstOrFail();
    (new ChangeItemReservation)($item, ChangeItemReservationData::from(['reserved_quantity' => '0']));
    (new ConvertToQuotation)($opportunity->refresh());
    (new ConvertToOrder)($opportunity->refresh());

    return $item->refresh();
}

function makeConcurrencyQuotationItem(Store $store, Product $product, string $subject): OpportunityItem
{
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => $subject,
        'store_id' => $store->id,
        'starts_at' => '2026-11-01T09:00:00Z',
        'ends_at' => '2026-11-03T17:00:00Z',
    ]));
    $opportunity = Opportunity::query()->findOrFail($created->id);

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => $product->name,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'item_type' => OpportunityItemType::Product->value,
        'quantity' => '10',
        'transaction_type' => LineItemTransactionType::Rental->value,
    ]));
    (new ConvertToQuotation)($opportunity->refresh());

    return $opportunity->refresh()->items()->firstOrFail();
}

/**
 * @return array<string, mixed>
 */
function decodeConcurrencyWorkerResult(string $output): array
{
    $result = json_decode($output, true, flags: JSON_THROW_ON_ERROR);

    if (! is_array($result)) {
        throw new RuntimeException('Concurrency worker returned a non-object result.');
    }

    $object = [];
    foreach ($result as $key => $value) {
        if (is_string($key)) {
            $object[$key] = $value;
        }
    }

    return $object;
}

/**
 * @param  array<int, array{item_id?: int, stock_level_id?: int, asset_id?: int, lock_scopes?: string, quantity: string, operation?: string}>  $requests
 * @return Collection<int, array<string, mixed>>
 */
function runReservationWorkers(array $requests): Collection
{
    $connection = DB::connection('pgsql_testing');
    while ($connection->transactionLevel() > 0) {
        $connection->commit();
    }

    $barrier = storage_path('framework/testing-capacity-barrier-'.bin2hex(random_bytes(8)));
    $worker = base_path('tests/Pgsql/Support/ReservationConcurrencyWorker.php');
    $processes = collect($requests)->map(static fn (array $request): Process => new Process([
        PHP_BINARY,
        $worker,
        $request['operation'] ?? 'reserve',
        (string) ($request['lock_scopes'] ?? $request['item_id'] ?? $request['stock_level_id'] ?? $request['asset_id']),
        $request['quantity'],
        $barrier,
    ], base_path()))->all();

    try {
        foreach ($processes as $process) {
            $process->start();
        }

        $readyByProcess = array_fill(0, count($processes), false);
        for ($attempt = 0; $attempt < 500 && in_array(false, $readyByProcess, true); $attempt++) {
            foreach ($processes as $index => $process) {
                if (str_contains($process->getIncrementalOutput(), "ready\n")) {
                    $readyByProcess[$index] = true;
                }
            }
            if (in_array(false, $readyByProcess, true)) {
                usleep(10000);
            }
        }

        expect($readyByProcess)->each->toBeTrue();
        file_put_contents($barrier, 'go');

        foreach ($processes as $process) {
            $process->wait();
        }

        return collect($processes)->map(static function (Process $process): array {
            $lines = array_values(array_filter(explode("\n", trim($process->getOutput()))));

            return decodeConcurrencyWorkerResult((string) end($lines));
        });
    } finally {
        if (file_exists($barrier)) {
            unlink($barrier);
        }

        foreach ($processes as $process) {
            if ($process->isRunning()) {
                $process->stop(1);
            }
        }

        $connection->beginTransaction();
    }
}

it('serializes competing reservation commands through real independent processes', function (): void {
    $itemA = makeConcurrencyItem($this->store, $this->product, 'Concurrency A');
    $itemB = makeConcurrencyItem($this->store, $this->product, 'Concurrency B');

    $results = runReservationWorkers([
        ['item_id' => $itemA->id, 'quantity' => '6'],
        ['item_id' => $itemB->id, 'quantity' => '6'],
    ]);

    try {
        expect($results->where('ok', true))->toHaveCount(1, json_encode($results->all(), JSON_THROW_ON_ERROR))
            ->and($results->where('ok', false))->toHaveCount(1)
            ->and((float) OpportunityItem::query()->whereIn('id', [$itemA->id, $itemB->id])->sum('reserved_quantity'))->toBe(6.0)
            ->and(Demand::query()->whereIn('source_id', [$itemA->id, $itemB->id])->sum('quantity'))->toBe(6);
    } finally {
        Opportunity::query()->whereIn('id', [$itemA->opportunity_id, $itemB->opportunity_id])->withTrashed()->forceDelete();
        StockLevel::query()->where('product_id', $this->product->id)->delete();
        Product::query()->whereKey($this->product->id)->delete();
        Store::query()->whereKey($this->store->id)->delete();
    }
});

it('serializes competing reservations for the final remaining capacity', function (): void {
    $existing = makeConcurrencyItem($this->store, $this->product, 'Existing reservation');
    (new ChangeItemReservation)($existing, ChangeItemReservationData::from(['reserved_quantity' => '8']));

    $itemA = makeConcurrencyItem($this->store, $this->product, 'Final capacity A');
    $itemB = makeConcurrencyItem($this->store, $this->product, 'Final capacity B');
    $results = runReservationWorkers([
        ['item_id' => $itemA->id, 'quantity' => '2'],
        ['item_id' => $itemB->id, 'quantity' => '2'],
    ]);

    try {
        expect($results->where('ok', true))->toHaveCount(1, json_encode($results->all(), JSON_THROW_ON_ERROR))
            ->and($results->where('ok', false))->toHaveCount(1)
            ->and((float) OpportunityItem::query()->whereIn('id', [$existing->id, $itemA->id, $itemB->id])->sum('reserved_quantity'))->toBe(10.0)
            ->and(Demand::query()->whereIn('source_id', [$existing->id, $itemA->id, $itemB->id])->sum('quantity'))->toBe(10);
    } finally {
        Opportunity::query()->whereIn('id', [$existing->opportunity_id, $itemA->opportunity_id, $itemB->opportunity_id])->withTrashed()->forceDelete();
        StockLevel::query()->where('product_id', $this->product->id)->delete();
        Product::query()->whereKey($this->product->id)->delete();
        Store::query()->whereKey($this->store->id)->delete();
    }
});

it('serializes concurrent reservation changes on the same item without a lost update', function (): void {
    $item = makeConcurrencyItem($this->store, $this->product, 'Same line reservation');
    (new ChangeItemReservation)($item, ChangeItemReservationData::from(['reserved_quantity' => '4']));

    $results = runReservationWorkers([
        ['item_id' => $item->id, 'quantity' => '6'],
        ['item_id' => $item->id, 'quantity' => '7'],
    ]);

    try {
        expect($results->where('ok', true))->toHaveCount(2, json_encode($results->all(), JSON_THROW_ON_ERROR))
            ->and((float) $item->refresh()->reserved_quantity)->toBeIn([6.0, 7.0])
            ->and((float) Demand::query()->where('source_id', $item->id)->sum('quantity'))->toBe((float) $item->fresh()->reserved_quantity);
    } finally {
        Opportunity::query()->whereKey($item->opportunity_id)->withTrashed()->forceDelete();
        StockLevel::query()->where('product_id', $this->product->id)->delete();
        Product::query()->whereKey($this->product->id)->delete();
        Store::query()->whereKey($this->store->id)->delete();
    }
});

it('does not let confirmed VirtualStock authorize an owned reservation', function (): void {
    StockLevel::query()->where('product_id', $this->product->id)->update(['quantity_held' => 6]);

    $intakeResult = (new CreateVirtualStockIntake)(CreateVirtualStockIntakeData::from([
        'product_id' => $this->product->id,
        'store_id' => $this->store->id,
        'quantity' => 4,
        'available_from' => '2026-11-01T09:00:00Z',
        'available_until' => '2026-11-03T17:00:00Z',
        'unit_cost' => 100,
    ]));
    $intake = VirtualStockIntake::query()->findOrFail($intakeResult->id);
    app(ConfirmVirtualStockIntake::class)($intake);

    $item = makeConcurrencyItem($this->store, $this->product, 'External supply control');

    expect(fn () => (new ChangeItemReservation)($item, ChangeItemReservationData::from(['reserved_quantity' => '10'])))
        ->toThrow(ValidationException::class);

    (new ChangeItemReservation)($item->refresh(), ChangeItemReservationData::from(['reserved_quantity' => '6']));

    expect((float) $item->refresh()->reserved_quantity)->toBe(6.0)
        ->and((float) Demand::query()->where('source_id', $item->id)->sum('quantity'))->toBe(6.0);
});

it('serializes reservation increases with owned stock reductions', function (): void {
    $existing = makeConcurrencyItem($this->store, $this->product, 'Stock reduction existing');
    (new ChangeItemReservation)($existing, ChangeItemReservationData::from(['reserved_quantity' => '6']));
    $candidate = makeConcurrencyItem($this->store, $this->product, 'Stock reduction candidate');
    $stockLevel = StockLevel::query()->where('product_id', $this->product->id)->firstOrFail();

    $results = runReservationWorkers([
        ['item_id' => $candidate->id, 'quantity' => '5'],
        ['operation' => 'stock_reduce', 'stock_level_id' => $stockLevel->id, 'quantity' => '4'],
    ]);

    try {
        expect($results->where('ok', true)->count())->toBeGreaterThanOrEqual(1)
            ->and($results->where('ok', true)->count())->toBeLessThanOrEqual(2)
            ->and((float) $stockLevel->refresh()->quantity_held)->toBe(4.0)
            ->and((float) Demand::query()->whereIn('source_id', [$existing->id, $candidate->id])->sum('quantity'))
            ->toBeIn([6.0, 11.0]);

        if ((float) Demand::query()->whereIn('source_id', [$existing->id, $candidate->id])->sum('quantity') === 6.0) {
            expect((float) Demand::query()->where('source_id', $candidate->id)->sum('quantity'))->toBe(0.0);
        }
    } finally {
        Opportunity::query()->whereIn('id', [$existing->opportunity_id, $candidate->opportunity_id])->withTrashed()->forceDelete();
        StockLevel::query()->where('product_id', $this->product->id)->delete();
        Product::query()->whereKey($this->product->id)->delete();
        Store::query()->whereKey($this->store->id)->delete();
    }
});

it('preserves allocated-not-greater-than-reserved under concurrent reduction and allocation', function (): void {
    $serialProduct = Product::factory()->rental()->serialised()->create();
    $ownedAssetA = Asset::factory()->create(['product_id' => $serialProduct->id]);
    $ownedAssetB = Asset::factory()->create(['product_id' => $serialProduct->id]);
    $assetA = StockLevel::factory()->serialised()->create([
        'product_id' => $serialProduct->id,
        'store_id' => $this->store->id,
        'asset_id' => $ownedAssetA->id,
    ]);
    $assetB = StockLevel::factory()->serialised()->create([
        'product_id' => $serialProduct->id,
        'store_id' => $this->store->id,
        'asset_id' => $ownedAssetB->id,
    ]);
    $item = makeConcurrencyItem($this->store, $serialProduct, 'Allocation race');
    (new ChangeItemReservation)($item, ChangeItemReservationData::from(['reserved_quantity' => '2']));
    (new AllocateAsset)($item->refresh(), AllocateAssetData::from(['stock_level_id' => $assetA->id]));

    $results = runReservationWorkers([
        ['item_id' => $item->id, 'quantity' => '1'],
        ['operation' => 'allocate', 'item_id' => $item->id, 'quantity' => (string) $assetB->id],
    ]);

    try {
        $item->refresh();
        $allocated = OpportunityItemAsset::query()->where('opportunity_item_id', $item->id)->count();

        expect($results->where('ok', true)->count())->toBe(1)
            ->and($allocated)->toBeLessThanOrEqual((int) $item->reserved_quantity)
            ->and(Demand::query()->where('source_id', $item->id)->sum('quantity'))->toBe((int) $item->reserved_quantity);
    } finally {
        Opportunity::query()->whereKey($item->opportunity_id)->withTrashed()->forceDelete();
        StockLevel::query()->whereIn('id', [$assetA->id, $assetB->id])->delete();
        Asset::query()->whereIn('id', [$ownedAssetA->id, $ownedAssetB->id])->delete();
        Product::query()->whereKey($serialProduct->id)->delete();
        StockLevel::query()->where('product_id', $this->product->id)->delete();
        Product::query()->whereKey($this->product->id)->delete();
        Store::query()->whereKey($this->store->id)->delete();
    }
});

it('normalizes reversed multi-scope lock acquisition without deadlock', function (): void {
    $otherStore = Store::factory()->create(['timezone' => 'UTC']);
    $scopes = [
        ['product_id' => $this->product->id, 'store_id' => $this->store->id],
        ['product_id' => $this->product->id, 'store_id' => $otherStore->id],
    ];

    try {
        $results = runReservationWorkers([
            ['operation' => 'lock_order', 'lock_scopes' => json_encode($scopes, JSON_THROW_ON_ERROR), 'quantity' => '0'],
            ['operation' => 'lock_order', 'lock_scopes' => json_encode(array_reverse($scopes), JSON_THROW_ON_ERROR), 'quantity' => '0'],
        ]);

        expect($results->where('ok', true))->toHaveCount(2, json_encode($results->all(), JSON_THROW_ON_ERROR));
    } finally {
        Store::query()->whereKey($otherStore->id)->delete();
    }
});

it('serializes reservation against serialized lifecycle supply removal', function (): void {
    $serialProduct = Product::factory()->rental()->serialised()->create();
    $ownedAssetA = Asset::factory()->create(['product_id' => $serialProduct->id]);
    $ownedAssetB = Asset::factory()->create(['product_id' => $serialProduct->id]);
    $assetA = StockLevel::factory()->serialised()->create([
        'product_id' => $serialProduct->id,
        'store_id' => $this->store->id,
        'asset_id' => $ownedAssetA->id,
    ]);
    $assetB = StockLevel::factory()->serialised()->create([
        'product_id' => $serialProduct->id,
        'store_id' => $this->store->id,
        'asset_id' => $ownedAssetB->id,
    ]);
    $existing = makeConcurrencyItem($this->store, $serialProduct, 'Lifecycle existing');
    (new ChangeItemReservation)($existing, ChangeItemReservationData::from(['reserved_quantity' => '1']));
    $candidate = makeConcurrencyItem($this->store, $serialProduct, 'Lifecycle candidate');

    $results = runReservationWorkers([
        ['item_id' => $candidate->id, 'quantity' => '1'],
        ['operation' => 'lifecycle_missing', 'asset_id' => $ownedAssetB->id, 'quantity' => '0'],
    ]);

    try {
        $candidate->refresh();
        $existing->refresh();
        expect($results->where('ok', true)->count())->toBeGreaterThanOrEqual(1, json_encode($results->all(), JSON_THROW_ON_ERROR))
            ->and(AssetLifecycleEvent::query()->where('asset_id', $ownedAssetB->id)->latest('id')->firstOrFail()->new_status)->toBe(AssetLifecycleStatus::Missing)
            ->and((float) Demand::query()->whereIn('source_id', [$existing->id, $candidate->id])->sum('quantity'))
            ->toBe((float) ($existing->reserved_quantity + ($candidate->reserved_quantity ?? 0)));
    } finally {
        Opportunity::query()->whereIn('id', [$existing->opportunity_id, $candidate->opportunity_id])->withTrashed()->forceDelete();
        StockLevel::query()->whereIn('id', [$assetA->id, $assetB->id])->delete();
        Asset::query()->whereIn('id', [$ownedAssetA->id, $ownedAssetB->id])->delete();
        Product::query()->whereKey($serialProduct->id)->delete();
        StockLevel::query()->where('product_id', $this->product->id)->delete();
        Product::query()->whereKey($this->product->id)->delete();
        Store::query()->whereKey($this->store->id)->delete();
    }
});

it('serializes status activation with a competing reservation without duplicate demand', function (): void {
    $existing = makeConcurrencyItem($this->store, $this->product, 'Status existing');
    (new ChangeItemReservation)($existing, ChangeItemReservationData::from(['reserved_quantity' => '9']));
    $candidate = makeConcurrencyItem($this->store, $this->product, 'Status candidate');
    $quotationItem = makeConcurrencyQuotationItem($this->store, $this->product, 'Status activation');

    $results = runReservationWorkers([
        ['operation' => 'status_reserved', 'item_id' => $quotationItem->opportunity_id, 'quantity' => '0'],
        ['item_id' => $candidate->id, 'quantity' => '1'],
    ]);

    try {
        $demandSources = Demand::query()
            ->whereIn('source_id', [$existing->id, $candidate->id, $quotationItem->id])
            ->where('is_active', true)
            ->get()
            ->groupBy('source_id');

        expect($results->where('ok', true)->count())->toBeGreaterThanOrEqual(1)
            ->and($demandSources->get($existing->id))->toHaveCount(1)
            ->and($demandSources->get($quotationItem->id))->toHaveCount(1)
            ->and($demandSources->every(fn ($demands) => $demands->count() === 1))->toBeTrue()
            ->and($quotationItem->opportunity()->firstOrFail()->refresh()->getRawOriginal('status'))->toBe(OpportunityStatus::QuotationReserved->statusValue());
    } finally {
        Opportunity::query()->whereIn('id', [$existing->opportunity_id, $candidate->opportunity_id, $quotationItem->opportunity_id])->withTrashed()->forceDelete();
        StockLevel::query()->where('product_id', $this->product->id)->delete();
        Product::query()->whereKey($this->product->id)->delete();
        Store::query()->whereKey($this->store->id)->delete();
    }
});

it('resolves an active Store move while a destination reservation runs concurrently', function (): void {
    $destination = Store::factory()->create(['timezone' => 'UTC']);
    StockLevel::factory()->bulk()->create([
        'product_id' => $this->product->id,
        'store_id' => $destination->id,
        'quantity_held' => 10,
    ]);
    $moving = makeConcurrencyItem($this->store, $this->product, 'Store move');
    (new ChangeItemReservation)($moving, ChangeItemReservationData::from(['reserved_quantity' => '6']));
    $destinationItem = makeConcurrencyItem($destination, $this->product, 'Store destination');
    $destinationItem->opportunity()->update(['number' => '0000000002']);

    $results = runReservationWorkers([
        ['operation' => 'store_move', 'item_id' => $moving->opportunity_id, 'quantity' => (string) $destination->id],
        ['item_id' => $destinationItem->id, 'quantity' => '6'],
    ]);

    try {
        $movingDemand = Demand::query()->where('source_id', $moving->id)->where('is_active', true)->get();
        $destinationDemand = Demand::query()->where('source_id', $destinationItem->id)->where('is_active', true)->get();

        expect($results->where('ok', true))->toHaveCount(2, json_encode($results->all(), JSON_THROW_ON_ERROR))
            ->and($movingDemand)->toHaveCount(1)
            ->and($movingDemand->first()->store_id)->toBe($destination->id)
            ->and($destinationDemand)->toHaveCount(1)
            ->and($destinationDemand->first()->store_id)->toBe($destination->id)
            ->and($movingDemand->pluck('store_id')->merge($destinationDemand->pluck('store_id')))->each->toBe($destination->id);
    } finally {
        Opportunity::query()->whereIn('id', [$moving->opportunity_id, $destinationItem->opportunity_id])->withTrashed()->forceDelete();
        StockLevel::query()->where('product_id', $this->product->id)->delete();
        Product::query()->whereKey($this->product->id)->delete();
        Store::query()->whereKey($destination->id)->delete();
        Store::query()->whereKey($this->store->id)->delete();
    }
});
