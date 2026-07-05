<?php

use App\Actions\Containers\DispatchContainer;
use App\Actions\Containers\PackContainerItem;
use App\Actions\Containers\RepackReturnItem;
use App\Actions\Containers\SealContainer;
use App\Actions\Containers\StartRepackOnReturn;
use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\AllocateAsset;
use App\Actions\Opportunities\ConvertToOrder;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\DispatchAsset;
use App\Contracts\Availability\AvailabilityResolutionProvider;
use App\Data\Containers\DispatchContainerData;
use App\Data\Containers\PackContainerItemData;
use App\Data\Containers\RepackReturnItemData;
use App\Data\Containers\StartRepackOnReturnData;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\AllocateAssetData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\DispatchAssetData;
use App\Enums\AssetAssignmentStatus;
use App\Enums\AvailabilityResolution;
use App\Enums\ContainerAvailabilityMode;
use App\Enums\ContainerScanMode;
use App\Enums\ContainerStatus;
use App\Enums\KitComponentBinding;
use App\Enums\LineItemTransactionType;
use App\Events\AuditableEvent;
use App\Models\Container;
use App\Models\ContainerItem;
use App\Models\Opportunity;
use App\Models\OpportunityItemAsset;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Volt;
use Thunk\Verbs\Facades\Verbs;

beforeEach(function () {
    Queue::fake();
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());

    $this->app->bind(AvailabilityResolutionProvider::class, fn () => new class implements AvailabilityResolutionProvider
    {
        public function resolve(): AvailabilityResolution
        {
            return AvailabilityResolution::Daily;
        }
    });

    $this->store = Store::factory()->create(['timezone' => 'UTC']);
});

function p54RepackProduct(Store $store): Product
{
    return Product::factory()->containerable(ContainerAvailabilityMode::Transport)->create([
        'container_repack_on_return' => true,
        'container_template' => [
            'slots' => [
                [
                    'product_id' => Product::factory()->serialised()->create()->id,
                    'quantity' => 1,
                    'binding' => KitComponentBinding::Pool->value,
                ],
            ],
        ],
    ]);
}

/**
 * @return array{product: Product, housing: StockLevel, container: Container}
 */
function p54TransportCase(Store $store, ?Product $product = null): array
{
    $product ??= p54RepackProduct($store);
    $housing = StockLevel::factory()->serialised()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
    ]);
    $container = Container::factory()->transport()->create([
        'product_id' => $product->id,
        'serialised_item_id' => $housing->id,
        'store_id' => $store->id,
        'scan_mode' => ContainerScanMode::Free->value,
    ]);

    return compact('product', 'housing', 'container');
}

/**
 * @param  array<int, string>  $lineQuantities
 */
function p54DispatchOrder(Store $store, array $lineQuantities): Opportunity
{
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Repack job',
        'store_id' => $store->id,
        'starts_at' => '2026-09-01T09:00:00Z',
        'ends_at' => '2026-09-05T17:00:00Z',
    ]));
    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();
    (new ConvertToQuotation)($opportunity);

    foreach ($lineQuantities as $productId => $quantity) {
        $product = Product::query()->whereKey($productId)->firstOrFail();
        (new AddOpportunityItem)($opportunity->refresh(), AddOpportunityItemData::from([
            'name' => $product->name,
            'itemable_id' => $product->id,
            'itemable_type' => Product::class,
            'quantity' => $quantity,
            'transaction_type' => LineItemTransactionType::Rental->value,
        ]));
    }

    (new ConvertToOrder)($opportunity->refresh());

    return $opportunity->fresh(['items']);
}

function p54Pack(Container $container, StockLevel $asset): void
{
    (new PackContainerItem)($container, PackContainerItemData::from([
        'serialised_item_id' => $asset->id,
    ]));
}

function p54Seal(Container $container): void
{
    (new SealContainer)($container);
}

/**
 * @return array{product: Product, housing: StockLevel, container: Container, component: StockLevel, componentProduct: Product, opportunity: Opportunity}
 */
function p54DispatchedTransportCase(Store $store, ?Product $product = null): array
{
    ['product' => $product, 'housing' => $housing, 'container' => $container] = p54TransportCase($store, $product);

    $componentProduct = Product::factory()->serialised()->create();
    $component = StockLevel::factory()->serialised()->create([
        'product_id' => $componentProduct->id,
        'store_id' => $store->id,
    ]);
    p54Pack($container, $component);
    p54Seal($container);

    $opportunity = p54DispatchOrder($store, [
        $componentProduct->id => '1',
        $product->id => '1',
    ]);

    (new DispatchContainer)($container, DispatchContainerData::from([
        'opportunity_id' => $opportunity->id,
    ]));

    return compact('product', 'housing', 'container', 'component', 'componentProduct', 'opportunity');
}

describe('StartRepackOnReturn', function () {
    it('returns housing, creates lineage container, and fires repack.started webhook audit', function () {
        Event::fake([AuditableEvent::class]);

        ['housing' => $housing, 'container' => $dissolved, 'component' => $component, 'opportunity' => $opportunity] = p54DispatchedTransportCase($this->store);

        $eventsBefore = verbEventCount();

        $result = (new StartRepackOnReturn)(StartRepackOnReturnData::from([
            'opportunity_id' => $opportunity->id,
            'serialised_item_id' => $housing->id,
        ]));

        expect($result->previous_container_id)->toBe($dissolved->id)
            ->and($result->container->status)->toBe(ContainerStatus::Open->value)
            ->and($result->container->previous_container_id)->toBe($dissolved->id)
            ->and($result->container->scan_mode)->toBe(ContainerScanMode::Free->value)
            ->and(OpportunityItemAsset::query()->where('stock_level_id', $housing->id)->first()?->status)
            ->toBe(AssetAssignmentStatus::CheckedIn)
            ->and(Container::query()->active()->where('serialised_item_id', $housing->id)->count())->toBe(1);

        expect(verbEventCount())->toBeGreaterThan($eventsBefore);

        Event::assertDispatched(
            AuditableEvent::class,
            fn (AuditableEvent $event): bool => $event->action === 'container.repack.started',
        );
    });

    it('rejects when repack on return is disabled on the product', function () {
        $product = Product::factory()->containerable(ContainerAvailabilityMode::Transport)->create([
            'container_repack_on_return' => false,
        ]);
        ['housing' => $housing, 'opportunity' => $opportunity] = p54DispatchedTransportCase($this->store, $product);

        expect(fn () => (new StartRepackOnReturn)(StartRepackOnReturnData::from([
            'opportunity_id' => $opportunity->id,
            'serialised_item_id' => $housing->id,
        ])))->toThrow(ValidationException::class);
    });

    it('creates container without lineage when no dissolved predecessor exists', function () {
        $product = p54RepackProduct($this->store);
        $housing = StockLevel::factory()->serialised()->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
        ]);

        $opportunity = p54DispatchOrder($this->store, [$product->id => '1']);
        $item = $opportunity->items()->firstOrFail();
        $asset = StockLevel::factory()->serialised()->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
        ]);
        (new AllocateAsset)($item, AllocateAssetData::from(['stock_level_id' => $asset->id]));
        $row = OpportunityItemAsset::query()->where('stock_level_id', $asset->id)->sole();
        (new DispatchAsset)($row, DispatchAssetData::from([]));

        $result = (new StartRepackOnReturn)(StartRepackOnReturnData::from([
            'opportunity_id' => $opportunity->id,
            'serialised_item_id' => $asset->id,
        ]));

        expect($result->previous_container_id)->toBeNull()
            ->and($result->container->previous_container_id)->toBeNull()
            ->and($result->container->scan_mode)->toBe(ContainerScanMode::Strict->value);
    });
});

describe('RepackReturnItem', function () {
    it('returns and repacks an item that was in the dissolved predecessor manifest', function () {
        ['housing' => $housing, 'component' => $component, 'opportunity' => $opportunity] = p54DispatchedTransportCase($this->store);

        $started = (new StartRepackOnReturn)(StartRepackOnReturnData::from([
            'opportunity_id' => $opportunity->id,
            'serialised_item_id' => $housing->id,
        ]));

        $repackContainer = Container::query()->where('uuid', $started->container->uuid)->firstOrFail();
        $eventsBefore = verbEventCount();

        $result = (new RepackReturnItem)($repackContainer, RepackReturnItemData::from([
            'opportunity_id' => $opportunity->id,
            'serialised_item_id' => $component->id,
        ]));

        expect($result->packed)->toBeTrue()
            ->and($result->container_item?->returned_from_opportunity_id)->toBe($opportunity->id)
            ->and($result->container_item?->serialised_item_id)->toBe($component->id)
            ->and(OpportunityItemAsset::query()->where('stock_level_id', $component->id)->first()?->status)
            ->toBe(AssetAssignmentStatus::CheckedIn)
            ->and(ContainerItem::query()->where('container_id', $repackContainer->id)->whereNull('unpacked_at')->count())
            ->toBe(1);

        expect(verbEventCount())->toBeGreaterThan($eventsBefore);
    });

    it('returns but does not pack items that were not in the original dispatch manifest', function () {
        ['housing' => $housing, 'opportunity' => $opportunity] = p54DispatchedTransportCase($this->store);

        $extraProduct = Product::factory()->serialised()->create();
        (new AddOpportunityItem)($opportunity->refresh(), AddOpportunityItemData::from([
            'name' => $extraProduct->name,
            'itemable_id' => $extraProduct->id,
            'itemable_type' => Product::class,
            'quantity' => '1',
            'transaction_type' => LineItemTransactionType::Rental->value,
        ]));
        $extra = StockLevel::factory()->serialised()->create([
            'product_id' => $extraProduct->id,
            'store_id' => $this->store->id,
        ]);
        $item = $opportunity->items()->where('itemable_id', $extraProduct->id)->firstOrFail();
        (new AllocateAsset)($item, AllocateAssetData::from(['stock_level_id' => $extra->id]));
        $row = OpportunityItemAsset::query()->where('stock_level_id', $extra->id)->sole();
        (new DispatchAsset)($row, DispatchAssetData::from([]));

        $started = (new StartRepackOnReturn)(StartRepackOnReturnData::from([
            'opportunity_id' => $opportunity->id,
            'serialised_item_id' => $housing->id,
        ]));
        $repackContainer = Container::query()->where('uuid', $started->container->uuid)->firstOrFail();

        $result = (new RepackReturnItem)($repackContainer, RepackReturnItemData::from([
            'opportunity_id' => $opportunity->id,
            'serialised_item_id' => $extra->id,
        ]));

        expect($result->packed)->toBeFalse()
            ->and($result->container_item)->toBeNull()
            ->and(OpportunityItemAsset::query()->where('stock_level_id', $extra->id)->first()?->status)
            ->toBe(AssetAssignmentStatus::CheckedIn);
    });

    it('packs unexpected items when free_scan is enabled', function () {
        ['housing' => $housing, 'opportunity' => $opportunity] = p54DispatchedTransportCase($this->store);

        $extraProduct = Product::factory()->serialised()->create();
        (new AddOpportunityItem)($opportunity->refresh(), AddOpportunityItemData::from([
            'name' => $extraProduct->name,
            'itemable_id' => $extraProduct->id,
            'itemable_type' => Product::class,
            'quantity' => '1',
            'transaction_type' => LineItemTransactionType::Rental->value,
        ]));
        $extra = StockLevel::factory()->serialised()->create([
            'product_id' => $extraProduct->id,
            'store_id' => $this->store->id,
        ]);
        $item = $opportunity->items()->where('itemable_id', $extraProduct->id)->firstOrFail();
        (new AllocateAsset)($item, AllocateAssetData::from(['stock_level_id' => $extra->id]));
        $row = OpportunityItemAsset::query()->where('stock_level_id', $extra->id)->sole();
        (new DispatchAsset)($row, DispatchAssetData::from([]));

        $started = (new StartRepackOnReturn)(StartRepackOnReturnData::from([
            'opportunity_id' => $opportunity->id,
            'serialised_item_id' => $housing->id,
        ]));
        $repackContainer = Container::query()->where('uuid', $started->container->uuid)->firstOrFail();

        $result = (new RepackReturnItem)($repackContainer, RepackReturnItemData::from([
            'opportunity_id' => $opportunity->id,
            'serialised_item_id' => $extra->id,
            'free_scan' => true,
        ]));

        expect($result->packed)->toBeTrue()
            ->and($result->container_item?->returned_from_opportunity_id)->toBe($opportunity->id);
    });

    it('rejects repack on a container not created via repack-on-return', function () {
        $container = Container::factory()->transport()->create(['store_id' => $this->store->id]);

        expect(fn () => (new RepackReturnItem)($container, RepackReturnItemData::from([
            'opportunity_id' => 1,
            'serialised_item_id' => 1,
        ])))->toThrow(ValidationException::class);
    });

    it('replays opportunity projections identically after repack return item', function () {
        ['housing' => $housing, 'component' => $component, 'opportunity' => $opportunity] = p54DispatchedTransportCase($this->store);

        $started = (new StartRepackOnReturn)(StartRepackOnReturnData::from([
            'opportunity_id' => $opportunity->id,
            'serialised_item_id' => $housing->id,
        ]));
        $repackContainer = Container::query()->where('uuid', $started->container->uuid)->firstOrFail();

        (new RepackReturnItem)($repackContainer, RepackReturnItemData::from([
            'opportunity_id' => $opportunity->id,
            'serialised_item_id' => $component->id,
        ]));

        $componentRow = OpportunityItemAsset::query()->where('stock_level_id', $component->id)->firstOrFail();
        $snapshotBefore = [
            'id' => $componentRow->id,
            'status' => $componentRow->status->value,
            'returned_at' => $componentRow->returned_at?->toIso8601String(),
        ];
        $eventCountBefore = DB::table('verb_events')->count();

        OpportunityItemAsset::query()->whereKey($componentRow->id)->delete();

        Verbs::replay();

        $componentAfter = OpportunityItemAsset::query()->whereKey($componentRow->id)->firstOrFail();
        $snapshotAfter = [
            'id' => $componentAfter->id,
            'status' => $componentAfter->status->value,
            'returned_at' => $componentAfter->returned_at?->toIso8601String(),
        ];

        expect($snapshotAfter)->toBe($snapshotBefore)
            ->and(DB::table('verb_events')->count())->toBe($eventCountBefore);
    });

    it('forbids repack without return permission', function () {
        ['housing' => $housing, 'component' => $component, 'opportunity' => $opportunity] = p54DispatchedTransportCase($this->store);

        $started = (new StartRepackOnReturn)(StartRepackOnReturnData::from([
            'opportunity_id' => $opportunity->id,
            'serialised_item_id' => $housing->id,
        ]));
        $repackContainer = Container::query()->where('uuid', $started->container->uuid)->firstOrFail();

        $viewer = User::factory()->create();
        $viewer->givePermissionTo(['containers.view', 'containers.pack', 'stock.access']);
        $this->actingAs($viewer);

        expect(fn () => (new RepackReturnItem)($repackContainer, RepackReturnItemData::from([
            'opportunity_id' => $opportunity->id,
            'serialised_item_id' => $component->id,
        ])))->toThrow(AuthorizationException::class);
    });
});

describe('Repack on return API', function () {
    it('exposes start and item endpoints', function () {
        ['housing' => $housing, 'component' => $component, 'opportunity' => $opportunity] = p54DispatchedTransportCase($this->store);

        $start = $this->postJson('/api/v1/containers/repack/start', [
            'opportunity_id' => $opportunity->id,
            'serialised_item_id' => $housing->id,
        ])->assertSuccessful();

        $uuid = $start->json('repack.container.uuid');

        $this->postJson("/api/v1/containers/{$uuid}/repack", [
            'opportunity_id' => $opportunity->id,
            'serialised_item_id' => $component->id,
        ])->assertSuccessful()
            ->assertJsonPath('repack_item.packed', true);
    });
});

it('smokes the repack split view', function () {
    ['housing' => $housing, 'component' => $component, 'opportunity' => $opportunity] = p54DispatchedTransportCase($this->store);

    $repack = (new StartRepackOnReturn)(StartRepackOnReturnData::from([
        'opportunity_id' => $opportunity->id,
        'serialised_item_id' => $housing->id,
    ]));

    Volt::test('containers.repack', [
        'container' => Container::query()->whereKey($repack->container->id)->firstOrFail(),
        'opportunity' => $opportunity,
    ])
        ->assertSee('Outstanding on opportunity')
        ->assertSee('Rebuilt container')
        ->set('scanInput', $component->asset_number)
        ->call('scanReturnItem')
        ->assertHasNoErrors();
});
