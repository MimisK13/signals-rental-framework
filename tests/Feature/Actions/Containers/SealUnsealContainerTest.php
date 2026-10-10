<?php

use App\Actions\Containers\PackContainerItem;
use App\Actions\Containers\SealContainer;
use App\Actions\Containers\UnsealContainer;
use App\Contracts\Availability\AvailabilityResolutionProvider;
use App\Data\Containers\PackContainerItemData;
use App\Data\Containers\UnsealContainerData;
use App\Enums\AvailabilityResolution;
use App\Enums\ContainerScanMode;
use App\Enums\ContainerStatus;
use App\Enums\ContainerUnsealReason;
use App\Events\AuditableEvent;
use App\Events\Containers\BeforeContainerSealed;
use App\Events\Containers\ContainerSealed;
use App\Events\Containers\ContainerUnsealed;
use App\Jobs\DeliverWebhook;
use App\Models\Container;
use App\Models\ContainerItem;
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
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());

    Webhook::factory()->create([
        'user_id' => auth()->id(),
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

function sealNestedKitContainer(Store $store, ?int $parentId = null): Container
{
    $product = Product::factory()->containerable()->create();
    $housing = StockLevel::factory()->serialised()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
    ]);

    return Container::factory()->create([
        'product_id' => $product->id,
        'serialised_item_id' => $housing->id,
        'store_id' => $store->id,
        'status' => ContainerStatus::Open->value,
        'parent_container_id' => $parentId,
    ]);
}

function sealComponent(Store $store): Product
{
    return Product::factory()->serialised()->create();
}

function sealContainerWithTemplate(
    Store $store,
    Product $component,
    int $expectedQty = 2,
    ContainerScanMode $scanMode = ContainerScanMode::Strict,
): Container {
    $housingProduct = Product::factory()->containerable()->create([
        'container_template' => [
            'slots' => [
                ['product_id' => $component->id, 'quantity' => $expectedQty, 'binding' => 'fixed'],
            ],
            'max_items' => $expectedQty,
        ],
    ]);

    $housing = StockLevel::factory()->serialised()->create([
        'product_id' => $housingProduct->id,
        'store_id' => $store->id,
    ]);

    return Container::factory()->create([
        'product_id' => $housingProduct->id,
        'serialised_item_id' => $housing->id,
        'store_id' => $store->id,
        'scan_mode' => $scanMode->value,
        'status' => ContainerStatus::Open->value,
    ]);
}

function sealPackAsset(Container $container, Store $store, Product $product): StockLevel
{
    $asset = StockLevel::factory()->serialised()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
    ]);

    (new PackContainerItem)($container, PackContainerItemData::from([
        'serialised_item_id' => $asset->id,
    ]));

    return $asset;
}

it('seals an open container with packed items in free scan mode', function () {
    $container = Container::factory()->kit()->create([
        'store_id' => $this->store->id,
        'scan_mode' => ContainerScanMode::Free->value,
    ]);
    $asset = sealPackAsset($container, $this->store, Product::factory()->serialised()->create());

    $result = (new SealContainer)($container);

    expect($result->status)->toBe('sealed')
        ->and($container->fresh()->sealed_at)->not->toBeNull()
        ->and($container->fresh()->sealed_by_user_id)->toBe(auth()->id());
});

it('rejects sealing an empty container', function () {
    $container = Container::factory()->kit()->create(['store_id' => $this->store->id]);

    (new SealContainer)($container);
})->throws(ValidationException::class, 'at least one item');

it('rejects sealing an already sealed container', function () {
    $container = Container::factory()->kit()->sealed()->create(['store_id' => $this->store->id]);
    ContainerItem::factory()->create([
        'container_id' => $container->id,
        'serialised_item_id' => StockLevel::factory()->serialised()->create(['store_id' => $this->store->id])->id,
        'product_id' => Product::factory()->serialised()->create()->id,
    ]);

    (new SealContainer)($container);
})->throws(ValidationException::class, 'Only open containers');

it('rejects strict under-filled template with per-product detail', function () {
    $component = sealComponent($this->store);
    $container = sealContainerWithTemplate($this->store, $component, expectedQty: 2);
    sealPackAsset($container, $this->store, $component);

    try {
        (new SealContainer)($container);
        expect(false)->toBeTrue('Expected ValidationException');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey("products.{$component->id}")
            ->and($e->errors()["products.{$component->id}"][0])->toContain('under-filled');
    }
});

it('rejects strict over-filled template with per-product detail', function () {
    $component = sealComponent($this->store);
    $container = sealContainerWithTemplate($this->store, $component, expectedQty: 1);
    sealPackAsset($container, $this->store, $component);
    sealPackAsset($container, $this->store, $component);

    try {
        (new SealContainer)($container);
        expect(false)->toBeTrue('Expected ValidationException');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey("products.{$component->id}")
            ->and($e->errors()["products.{$component->id}"][0])->toContain('over-filled');
    }
});

it('rejects strict wrong-product template with per-product detail', function () {
    $component = sealComponent($this->store);
    $wrong = sealComponent($this->store);
    $container = sealContainerWithTemplate($this->store, $component, expectedQty: 1);
    sealPackAsset($container, $this->store, $wrong);

    try {
        (new SealContainer)($container);
        expect(false)->toBeTrue('Expected ValidationException');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey("products.{$component->id}")
            ->and($e->errors())->toHaveKey("products.{$wrong->id}");
    }
});

it('seals strict mode when contents exactly match the template', function () {
    $component = sealComponent($this->store);
    $container = sealContainerWithTemplate($this->store, $component, expectedQty: 2);
    sealPackAsset($container, $this->store, $component);
    sealPackAsset($container, $this->store, $component);

    $result = (new SealContainer)($container);

    expect($result->status)->toBe('sealed');
});

it('rejects sealing when a nested container is still open', function () {
    $root = sealNestedKitContainer($this->store);
    $child = sealNestedKitContainer($this->store);

    (new PackContainerItem)($root, PackContainerItemData::from([
        'serialised_item_id' => $child->serialised_item_id,
        'resolve' => 'confirm_nesting',
    ]));

    sealPackAsset($root, $this->store, Product::factory()->serialised()->create());

    (new SealContainer)($root);
})->throws(ValidationException::class, 'Nested container');

it('seals when nested containers are already sealed', function () {
    $root = sealNestedKitContainer($this->store);
    $root->update(['scan_mode' => ContainerScanMode::Free->value]);

    $child = sealNestedKitContainer($this->store);
    sealPackAsset($child, $this->store, Product::factory()->serialised()->create());
    (new SealContainer)($child);

    (new PackContainerItem)($root, PackContainerItemData::from([
        'serialised_item_id' => $child->serialised_item_id,
        'resolve' => 'confirm_nesting',
    ]));
    sealPackAsset($root, $this->store, Product::factory()->serialised()->create());

    $result = (new SealContainer)($root);

    expect($result->status)->toBe('sealed');
});

it('allows before_seal listeners to veto sealing', function () {
    Event::listen(BeforeContainerSealed::class, function (): void {
        throw ValidationException::withMessages([
            'container' => ['Seal vetoed by plugin.'],
        ]);
    });

    $container = Container::factory()->kit()->create([
        'store_id' => $this->store->id,
        'scan_mode' => ContainerScanMode::Free->value,
    ]);
    sealPackAsset($container, $this->store, Product::factory()->serialised()->create());

    (new SealContainer)($container);
})->throws(ValidationException::class, 'vetoed');

it('fires audit and webhook events when sealing', function () {
    Queue::fake([DeliverWebhook::class]);
    Event::fake([AuditableEvent::class, BeforeContainerSealed::class, ContainerSealed::class]);

    $container = Container::factory()->kit()->create([
        'store_id' => $this->store->id,
        'scan_mode' => ContainerScanMode::Free->value,
    ]);
    sealPackAsset($container, $this->store, Product::factory()->serialised()->create());

    (new SealContainer)($container);

    Event::assertDispatched(AuditableEvent::class, fn (AuditableEvent $event): bool => $event->action === 'container.sealed');
    Queue::assertPushed(DeliverWebhook::class, fn (DeliverWebhook $job): bool => $job->event === 'container.sealed');
});

it('unseals a sealed container with a valid reason', function () {
    $container = Container::factory()->kit()->sealed()->create(['store_id' => $this->store->id]);
    ContainerItem::factory()->create([
        'container_id' => $container->id,
        'serialised_item_id' => StockLevel::factory()->serialised()->create(['store_id' => $this->store->id])->id,
        'product_id' => Product::factory()->serialised()->create()->id,
    ]);

    $result = (new UnsealContainer)($container, UnsealContainerData::from([
        'reason' => ContainerUnsealReason::Manual->value,
    ]));

    expect($result->status)->toBe('open')
        ->and($container->fresh()->unsealed_at)->not->toBeNull();
});

it('requires a reason to unseal', function () {
    $container = Container::factory()->kit()->sealed()->create(['store_id' => $this->store->id]);

    UnsealContainerData::validate(['reason' => '']);
})->throws(ValidationException::class);

it('rejects an invalid unseal reason', function () {
    $container = Container::factory()->kit()->sealed()->create(['store_id' => $this->store->id]);

    UnsealContainerData::validate(['reason' => 'not-a-reason']);
})->throws(ValidationException::class);

it('rejects unsealing a non-sealed container', function () {
    $container = Container::factory()->kit()->create(['store_id' => $this->store->id]);

    (new UnsealContainer)($container, UnsealContainerData::from([
        'reason' => ContainerUnsealReason::Manual->value,
    ]));
})->throws(ValidationException::class, 'Only sealed');

it('fires audit and webhook events when unsealing', function () {
    Queue::fake([DeliverWebhook::class]);
    Event::fake([AuditableEvent::class, ContainerUnsealed::class]);

    $container = Container::factory()->kit()->sealed()->create(['store_id' => $this->store->id]);

    (new UnsealContainer)($container, UnsealContainerData::from([
        'reason' => ContainerUnsealReason::Transfer->value,
    ]));

    Event::assertDispatched(AuditableEvent::class, fn (AuditableEvent $event): bool => $event->action === 'container.unsealed');
    Queue::assertPushed(DeliverWebhook::class, fn (DeliverWebhook $job): bool => $job->event === 'container.unsealed');
});

it('requires containers.seal permission to seal', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['containers.view', 'containers.pack', 'stock.access']);
    $this->actingAs($user);

    $container = Container::factory()->kit()->create(['store_id' => $this->store->id]);
    sealPackAsset($container, $this->store, Product::factory()->serialised()->create());

    (new SealContainer)($container);
})->throws(AuthorizationException::class);

it('does not cascade unseal to nested containers', function () {
    $root = Container::factory()->kit()->sealed()->create(['store_id' => $this->store->id]);
    $child = Container::factory()->kit()->sealed()->create(['store_id' => $this->store->id]);

    (new UnsealContainer)($root, UnsealContainerData::from([
        'reason' => ContainerUnsealReason::Manual->value,
    ]));

    expect($root->fresh()->status)->toBe(ContainerStatus::Open)
        ->and($child->fresh()->status)->toBe(ContainerStatus::Sealed);
});
