<?php

use App\Actions\Products\CreateStockTransaction;
use App\Actions\Products\DeleteStockTransaction;
use App\Data\Products\CreateStockTransactionData;
use App\Enums\ContainerBulkMembershipEventType;
use App\Enums\TransactionType;
use App\Models\Asset;
use App\Models\Container;
use App\Models\ContainerBulkMembership;
use App\Models\ContainerBulkMembershipEvent;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockTransaction;
use App\Models\Store;
use App\Models\User;
use Carbon\CarbonInterface;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

function bulkStockLevel(Store $store, int $quantity = 40): StockLevel
{
    return StockLevel::factory()->bulk()->create([
        'product_id' => Product::factory()->bulk(),
        'store_id' => $store->id,
        'quantity_held' => $quantity,
    ]);
}

function temporaryContainer(Store $store): Container
{
    return Container::factory()->temporary()->create(['store_id' => $store->id]);
}

it('persists exact current bulk memberships and derives contained and loose quantities', function (): void {
    $store = Store::factory()->create();
    $stockLevel = bulkStockLevel($store);
    $first = temporaryContainer($store);
    $second = temporaryContainer($store);

    $membership = ContainerBulkMembership::create([
        'container_id' => $first->id,
        'stock_level_id' => $stockLevel->id,
        'quantity' => '25.00',
    ]);
    ContainerBulkMembership::create([
        'container_id' => $second->id,
        'stock_level_id' => $stockLevel->id,
        'quantity' => '10.00',
    ]);

    expect($membership->container->is($first))->toBeTrue()
        ->and($membership->stockLevel->is($stockLevel))->toBeTrue()
        ->and($stockLevel->refresh()->containedBulkQuantity())->toBe('35.00')
        ->and($stockLevel->looseBulkQuantity())->toBe('5.00');
});

it('rejects duplicate current membership and non-positive quantities', function (): void {
    $store = Store::factory()->create();
    $stockLevel = bulkStockLevel($store);
    $container = temporaryContainer($store);

    ContainerBulkMembership::create([
        'container_id' => $container->id,
        'stock_level_id' => $stockLevel->id,
        'quantity' => '5.00',
    ]);

    expect(fn () => ContainerBulkMembership::create([
        'container_id' => $container->id,
        'stock_level_id' => $stockLevel->id,
        'quantity' => '3.00',
    ]))->toThrow(QueryException::class)
        ->and(fn () => ContainerBulkMembership::create([
            'container_id' => $container->id,
            'stock_level_id' => $stockLevel->id,
            'quantity' => '0.00',
        ]))->toThrow(ValidationException::class);
});

it('rejects serialized virtual and asset-linked stock levels as bulk memberships', function (): void {
    $store = Store::factory()->create();
    $container = temporaryContainer($store);

    $serialized = StockLevel::factory()->serialised()->create([
        'product_id' => Product::factory()->serialised(),
        'store_id' => $store->id,
    ]);
    $virtual = StockLevel::factory()->virtual()->create([
        'product_id' => Product::factory()->serialised(),
        'store_id' => $store->id,
    ]);
    $assetLinked = bulkStockLevel($store);
    $assetLinked->update(['asset_id' => Asset::factory()->create()->id]);

    foreach ([$serialized, $virtual, $assetLinked] as $stockLevel) {
        expect(fn () => ContainerBulkMembership::create([
            'container_id' => $container->id,
            'stock_level_id' => $stockLevel->id,
            'quantity' => '1.00',
        ]))->toThrow(ValidationException::class);
    }
});

it('requires the container and exact stock level to share a store', function (): void {
    $stockStore = Store::factory()->create();
    $containerStore = Store::factory()->create();
    $stockLevel = bulkStockLevel($stockStore);
    $container = temporaryContainer($containerStore);

    expect(fn () => ContainerBulkMembership::create([
        'container_id' => $container->id,
        'stock_level_id' => $stockLevel->id,
        'quantity' => '1.00',
    ]))->toThrow(ValidationException::class);
});

it('rejects current containment that exceeds owned quantity', function (): void {
    $store = Store::factory()->create();
    $stockLevel = bulkStockLevel($store, 10);
    $container = temporaryContainer($store);

    expect(fn () => ContainerBulkMembership::create([
        'container_id' => $container->id,
        'stock_level_id' => $stockLevel->id,
        'quantity' => '11.00',
    ]))->toThrow(ValidationException::class);
});

it('persists immutable bulk containment history with enum and timestamp casts', function (): void {
    $store = Store::factory()->create();
    $stockLevel = bulkStockLevel($store);
    $source = temporaryContainer($store);
    $destination = temporaryContainer($store);

    $event = ContainerBulkMembershipEvent::create([
        'stock_level_id' => $stockLevel->id,
        'source_container_id' => $source->id,
        'destination_container_id' => $destination->id,
        'event_type' => ContainerBulkMembershipEventType::Move,
        'quantity' => '5.00',
        'occurred_at' => now(),
        'source_quantity_before' => '15.00',
        'source_quantity_after' => '10.00',
        'destination_quantity_before' => '10.00',
        'destination_quantity_after' => '15.00',
    ]);

    expect($event->event_type)->toBe(ContainerBulkMembershipEventType::Move)
        ->and($event->occurred_at)->toBeInstanceOf(CarbonInterface::class)
        ->and($event->sourceContainer->is($source))->toBeTrue()
        ->and($event->destinationContainer->is($destination))->toBeTrue()
        ->and(fn () => $event->update(['reason' => 'changed']))->toThrow(LogicException::class)
        ->and(fn () => $event->delete())->toThrow(LogicException::class);
});

it('rejects non-positive event quantities', function (): void {
    $store = Store::factory()->create();
    $stockLevel = bulkStockLevel($store);

    expect(fn () => ContainerBulkMembershipEvent::create([
        'stock_level_id' => $stockLevel->id,
        'event_type' => ContainerBulkMembershipEventType::Pack,
        'quantity' => '0.00',
        'occurred_at' => now(),
    ]))->toThrow(ValidationException::class);
});

it('allows a decrease to the containment floor and rejects a decrease below it', function (): void {
    $store = Store::factory()->create();
    $stockLevel = bulkStockLevel($store);
    $first = temporaryContainer($store);
    $second = temporaryContainer($store);
    ContainerBulkMembership::create(['container_id' => $first->id, 'stock_level_id' => $stockLevel->id, 'quantity' => '25']);
    ContainerBulkMembership::create(['container_id' => $second->id, 'stock_level_id' => $stockLevel->id, 'quantity' => '10']);

    (new CreateStockTransaction)(CreateStockTransactionData::from([
        'stock_level_id' => $stockLevel->id,
        'store_id' => $store->id,
        'transaction_type' => TransactionType::Decrease->value,
        'quantity' => '5',
    ]));

    expect((float) $stockLevel->refresh()->quantity_held)->toBe(35.0);

    expect(fn () => (new CreateStockTransaction)(CreateStockTransactionData::from([
        'stock_level_id' => $stockLevel->id,
        'store_id' => $store->id,
        'transaction_type' => TransactionType::Decrease->value,
        'quantity' => '6',
    ])))->toThrow(ValidationException::class);

    expect((float) $stockLevel->refresh()->quantity_held)->toBe(35.0)
        ->and(StockTransaction::query()->where('stock_level_id', $stockLevel->id)->count())->toBe(1);
});

it('allows stock increases without changing current containment', function (): void {
    $store = Store::factory()->create();
    $stockLevel = bulkStockLevel($store);
    $container = temporaryContainer($store);
    ContainerBulkMembership::create(['container_id' => $container->id, 'stock_level_id' => $stockLevel->id, 'quantity' => '35']);

    (new CreateStockTransaction)(CreateStockTransactionData::from([
        'stock_level_id' => $stockLevel->id,
        'store_id' => $store->id,
        'transaction_type' => TransactionType::Increase->value,
        'quantity' => '20',
    ]));

    expect((float) $stockLevel->refresh()->quantity_held)->toBe(60.0)
        ->and($stockLevel->containedBulkQuantity())->toBe('35.00')
        ->and($stockLevel->looseBulkQuantity())->toBe('25.00');
});

it('rejects a reversal that would cross the containment floor', function (): void {
    $store = Store::factory()->create();
    $stockLevel = bulkStockLevel($store);
    $container = temporaryContainer($store);
    ContainerBulkMembership::create(['container_id' => $container->id, 'stock_level_id' => $stockLevel->id, 'quantity' => '35']);
    $original = StockTransaction::factory()->buy()->create([
        'stock_level_id' => $stockLevel->id,
        'store_id' => $store->id,
        'quantity' => '6',
    ]);

    expect(fn () => (new DeleteStockTransaction)($original, 'Correction'))
        ->toThrow(ValidationException::class);

    expect((float) $stockLevel->refresh()->quantity_held)->toBe(40.0)
        ->and(StockTransaction::query()->where('reversal_of_id', $original->id)->exists())->toBeFalse();
});

it('keeps serialized stock quantity behavior unchanged when no bulk membership exists', function (): void {
    $store = Store::factory()->create();
    $stockLevel = StockLevel::factory()->serialised()->create([
        'product_id' => Product::factory()->serialised(),
        'store_id' => $store->id,
        'quantity_held' => 1,
    ]);

    expect($stockLevel->isEligibleForBulkContainment())->toBeFalse()
        ->and($stockLevel->containedBulkQuantity())->toBe('0.00')
        ->and($stockLevel->looseBulkQuantity())->toBe('1.00');
});
