<?php

use App\Actions\Containers\CreateContainer;
use App\Actions\Containers\CreateTemporaryContainer;
use App\Actions\Containers\PackContainerItem;
use App\Actions\Containers\UnpackContainerItem;
use App\Actions\Containers\UpdateContainer;
use App\Contracts\Availability\AvailabilityResolutionProvider;
use App\Data\Containers\CreateContainerData;
use App\Data\Containers\CreateTemporaryContainerData;
use App\Data\Containers\PackContainerItemData;
use App\Data\Containers\UnpackContainerItemData;
use App\Data\Containers\UpdateContainerData;
use App\Enums\AvailabilityResolution;
use App\Enums\ContainerItemUnpackReason;
use App\Enums\ContainerStatus;
use App\Events\AuditableEvent;
use App\Jobs\DeliverWebhook;
use App\Models\Container;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use App\Models\Webhook;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
    $this->actingAs($this->owner);

    Webhook::factory()->create([
        'user_id' => $this->owner->id,
        'events' => ['*'],
        'is_active' => true,
    ]);

    $this->app->bind(AvailabilityResolutionProvider::class, fn () => new class implements AvailabilityResolutionProvider
    {
        public function resolve(): AvailabilityResolution
        {
            return AvailabilityResolution::Daily;
        }
    });

    $this->store = Store::factory()->create(['timezone' => 'UTC']);
});

function crudHousingAsset(Store $store, ?Product $product = null): StockLevel
{
    $product ??= Product::factory()->containerable()->create();

    return StockLevel::factory()->serialised()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'item_name' => 'Flight Case',
        'asset_number' => 'FC-001',
        'barcode' => 'CASE-001',
    ]);
}

it('creates a container from a containerable serialised item', function () {
    Queue::fake([DeliverWebhook::class]);

    $housing = crudHousingAsset($this->store);

    $result = (new CreateContainer)(CreateContainerData::from([
        'serialised_item_id' => $housing->id,
        'notes' => 'Prep bay A',
    ]));

    expect($result->serialised_item_id)->toBe($housing->id)
        ->and($result->product_id)->toBe($housing->product_id)
        ->and($result->is_temporary)->toBeFalse()
        ->and($result->barcode)->toBe('CASE-001')
        ->and($result->status)->toBe(ContainerStatus::Open->value);

    assertActionLogged('container.created', Container::class, $result->id, auth()->id());

    Queue::assertPushed(DeliverWebhook::class, fn (DeliverWebhook $job): bool => $job->event === 'container.created');
});

it('rejects creating a container from a non-containerable product', function () {
    $product = Product::factory()->serialised()->create(['is_containerable' => false]);
    $housing = StockLevel::factory()->serialised()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
    ]);

    (new CreateContainer)(CreateContainerData::from([
        'serialised_item_id' => $housing->id,
    ]));
})->throws(ValidationException::class);

it('rejects duplicate active containers for the same housing item', function () {
    $housing = crudHousingAsset($this->store);
    Container::factory()->create([
        'serialised_item_id' => $housing->id,
        'product_id' => $housing->product_id,
        'store_id' => $this->store->id,
    ]);

    (new CreateContainer)(CreateContainerData::from([
        'serialised_item_id' => $housing->id,
    ]));
})->throws(ValidationException::class);

it('creates a temporary container with a unique barcode', function () {
    Queue::fake([DeliverWebhook::class]);

    $result = (new CreateTemporaryContainer)(CreateTemporaryContainerData::from([
        'barcode' => 'TEMP-BOX-42',
        'store_id' => $this->store->id,
        'name' => 'Cardboard box',
    ]));

    expect($result->is_temporary)->toBeTrue()
        ->and($result->barcode)->toBe('TEMP-BOX-42')
        ->and($result->serialised_item_id)->toBeNull()
        ->and($result->product_id)->toBeNull()
        ->and($result->scan_mode)->toBe('free');

    Queue::assertPushed(DeliverWebhook::class, fn (DeliverWebhook $job): bool => $job->event === 'container.created');
});

it('rejects temporary containers without a barcode', function () {
    (new CreateTemporaryContainer)(CreateTemporaryContainerData::from([
        'barcode' => '   ',
        'store_id' => $this->store->id,
    ]));
})->throws(ValidationException::class);

it('rejects duplicate barcodes among active containers', function () {
    Container::factory()->temporary()->create([
        'barcode' => 'TEMP-DUP',
        'store_id' => $this->store->id,
    ]);

    (new CreateTemporaryContainer)(CreateTemporaryContainerData::from([
        'barcode' => 'TEMP-DUP',
        'store_id' => $this->store->id,
    ]));
})->throws(ValidationException::class);

it('updates an open container and rejects sealed containers', function () {
    $container = Container::factory()->kit()->create(['store_id' => $this->store->id]);

    $result = (new UpdateContainer)($container, UpdateContainerData::from([
        'name' => 'Renamed case',
        'notes' => 'Updated notes',
    ]));

    expect($result->name)->toBe('Renamed case')
        ->and($result->notes)->toBe('Updated notes');

    $sealed = Container::factory()->kit()->sealed()->create(['store_id' => $this->store->id]);

    (new UpdateContainer)($sealed, UpdateContainerData::from([
        'name' => 'Should fail',
    ]));
})->throws(ValidationException::class);

it('fires audit and webhook events when packing and unpacking', function () {
    Queue::fake([DeliverWebhook::class]);
    Event::fake([AuditableEvent::class]);

    $container = Container::factory()->kit()->create(['store_id' => $this->store->id]);
    $asset = StockLevel::factory()->serialised()->create([
        'product_id' => Product::factory()->serialised()->create()->id,
        'store_id' => $this->store->id,
    ]);

    (new PackContainerItem)($container, PackContainerItemData::from([
        'serialised_item_id' => $asset->id,
    ]));

    Event::assertDispatched(AuditableEvent::class, fn (AuditableEvent $event): bool => $event->action === 'container.item.packed');
    Queue::assertPushed(DeliverWebhook::class, fn (DeliverWebhook $job): bool => $job->event === 'container.item.packed');

    (new UnpackContainerItem)($container, UnpackContainerItemData::from([
        'serialised_item_id' => $asset->id,
        'reason' => ContainerItemUnpackReason::Manual->value,
    ]));

    Event::assertDispatched(AuditableEvent::class, fn (AuditableEvent $event): bool => $event->action === 'container.item.unpacked');
    Queue::assertPushed(DeliverWebhook::class, fn (DeliverWebhook $job): bool => $job->event === 'container.item.unpacked');
});

it('requires create permission for product-backed containers', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['containers.view', 'stock.access']);
    $this->actingAs($user);

    $housing = crudHousingAsset($this->store);

    (new CreateContainer)(CreateContainerData::from([
        'serialised_item_id' => $housing->id,
    ]));
})->throws(AuthorizationException::class);

it('requires create_temporary permission for temporary containers', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['containers.view', 'stock.access', 'containers.create']);
    $this->actingAs($user);

    (new CreateTemporaryContainer)(CreateTemporaryContainerData::from([
        'barcode' => 'TEMP-ONLY',
        'store_id' => $this->store->id,
    ]));
})->throws(AuthorizationException::class);
