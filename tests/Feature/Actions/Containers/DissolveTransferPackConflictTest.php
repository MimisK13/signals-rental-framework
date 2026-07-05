<?php

use App\Actions\Containers\DissolveContainer;
use App\Actions\Containers\PackContainerItem;
use App\Actions\Containers\TransferContainerItem;
use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\AllocateAsset;
use App\Actions\Opportunities\ConvertToOrder;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\DispatchAsset;
use App\Contracts\Availability\AvailabilityResolutionProvider;
use App\Data\Containers\PackContainerItemData;
use App\Data\Containers\TransferContainerItemData;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\AllocateAssetData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\DispatchAssetData;
use App\Enums\AssetAssignmentStatus;
use App\Enums\AvailabilityResolution;
use App\Enums\ContainerItemUnpackReason;
use App\Enums\ContainerStatus;
use App\Enums\LineItemTransactionType;
use App\Events\AuditableEvent;
use App\Exceptions\Containers\ContainerPackConflictException;
use App\Models\Container;
use App\Models\ContainerItem;
use App\Models\Demand;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\OpportunityItemAsset;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use App\Models\VirtualStockIntake;
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

function p51KitContainer(Store $store, ?int $parentId = null, int $maxDepth = 3): Container
{
    $product = Product::factory()->containerable()->create([
        'container_max_nesting_depth' => $maxDepth,
    ]);
    $housing = StockLevel::factory()->serialised()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
    ]);

    return Container::factory()->create([
        'product_id' => $product->id,
        'serialised_item_id' => $housing->id,
        'store_id' => $store->id,
        'parent_container_id' => $parentId,
    ]);
}

function p51PackAsset(Container $container, StockLevel $asset, ?string $resolve = null): void
{
    $payload = ['serialised_item_id' => $asset->id];

    if ($resolve !== null) {
        $payload['resolve'] = $resolve;
    }

    (new PackContainerItem)($container, PackContainerItemData::from($payload));
}

function p51DispatchedAsset(Store $store, Product $product): OpportunityItemAsset
{
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Pack conflict job',
        'store_id' => $store->id,
        'starts_at' => '2026-09-01T09:00:00Z',
        'ends_at' => '2026-09-05T17:00:00Z',
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
    $item = $opportunity->items()->firstOrFail();
    $asset = StockLevel::factory()->serialised()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
    ]);
    (new AllocateAsset)($item, AllocateAssetData::from(['stock_level_id' => $asset->id]));
    $row = OpportunityItemAsset::query()->where('stock_level_id', $asset->id)->sole();
    (new DispatchAsset)($row, DispatchAssetData::from([]));

    return $row->refresh();
}

describe('DissolveContainer', function () {
    it('guards against dissolving an already dissolved container', function () {
        $container = p51KitContainer($this->store);
        $container->update(['status' => ContainerStatus::Dissolved->value]);

        expect(fn () => (new DissolveContainer)($container))
            ->toThrow(ValidationException::class);
    });

    it('soft-closes memberships and releases container demands', function () {
        $container = p51KitContainer($this->store);
        $asset = StockLevel::factory()->serialised()->create([
            'product_id' => Product::factory()->serialised()->create()->id,
            'store_id' => $this->store->id,
        ]);
        p51PackAsset($container, $asset);

        expect(Demand::query()->where('source_type', 'container')->where('asset_id', $asset->id)->count())->toBe(1);

        (new DissolveContainer)($container);

        $membership = ContainerItem::query()->where('serialised_item_id', $asset->id)->firstOrFail();

        expect($membership->unpacked_at)->not->toBeNull()
            ->and($membership->unpacked_reason)->toBe(ContainerItemUnpackReason::Dissolved)
            ->and($container->fresh()->status)->toBe(ContainerStatus::Dissolved)
            ->and(Demand::query()->where('source_type', 'container')->where('asset_id', $asset->id)->count())->toBe(0);
    });

    it('cascades dissolve through nested containers at depth three', function () {
        $depth3 = p51KitContainer($this->store);
        $depth3Asset = StockLevel::factory()->serialised()->create([
            'product_id' => Product::factory()->serialised()->create()->id,
            'store_id' => $this->store->id,
        ]);
        p51PackAsset($depth3, $depth3Asset);

        $depth2 = p51KitContainer($this->store);
        p51PackAsset($depth2, StockLevel::query()->findOrFail($depth3->serialised_item_id), 'confirm_nesting');

        $root = p51KitContainer($this->store);
        p51PackAsset($root, StockLevel::query()->findOrFail($depth2->serialised_item_id), 'confirm_nesting');

        (new DissolveContainer)($root);

        expect($root->fresh()->status)->toBe(ContainerStatus::Dissolved)
            ->and($depth2->fresh()->status)->toBe(ContainerStatus::Dissolved)
            ->and($depth3->fresh()->status)->toBe(ContainerStatus::Dissolved)
            ->and(ContainerItem::query()->whereNull('unpacked_at')->count())->toBe(0);
    });

    it('dissolves sealed containers without requiring a separate unseal step', function () {
        $container = p51KitContainer($this->store);
        $asset = StockLevel::factory()->serialised()->create([
            'product_id' => Product::factory()->serialised()->create()->id,
            'store_id' => $this->store->id,
        ]);
        p51PackAsset($container, $asset);
        $container->update(['status' => ContainerStatus::Sealed->value, 'sealed_at' => now()]);

        (new DissolveContainer)($container);

        expect($container->fresh()->status)->toBe(ContainerStatus::Dissolved);
    });

    it('fires container.dissolved audit event', function () {
        Event::fake([AuditableEvent::class]);

        $container = p51KitContainer($this->store);
        p51PackAsset($container, StockLevel::factory()->serialised()->create([
            'product_id' => Product::factory()->serialised()->create()->id,
            'store_id' => $this->store->id,
        ]));

        (new DissolveContainer)($container);

        Event::assertDispatched(
            AuditableEvent::class,
            fn (AuditableEvent $event): bool => $event->action === 'container.dissolved',
        );
    });

    it('forbids dissolve without containers.dissolve permission', function () {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo(['containers.view', 'containers.pack', 'stock.access']);
        $this->actingAs($viewer);

        $container = p51KitContainer($this->store);

        expect(fn () => (new DissolveContainer)($container))
            ->toThrow(AuthorizationException::class);
    });
});

describe('PackContainerItem conflicts', function () {
    it('returns already_in_container conflict with transfer resolution', function () {
        $source = p51KitContainer($this->store);
        $target = p51KitContainer($this->store);
        $asset = StockLevel::factory()->serialised()->create([
            'product_id' => Product::factory()->serialised()->create()->id,
            'store_id' => $this->store->id,
        ]);
        p51PackAsset($source, $asset);

        try {
            (new PackContainerItem)($target, PackContainerItemData::from([
                'serialised_item_id' => $asset->id,
            ]));
            expect(false)->toBeTrue('Expected conflict');
        } catch (ContainerPackConflictException $e) {
            expect($e->conflict)->toBe('already_in_container')
                ->and($e->resolutions)->toHaveKey('transfer')
                ->and($e->resolutions['transfer']['available'])->toBeTrue();
        }
    });

    it('returns checked_out conflict for dispatched assets', function () {
        $product = Product::factory()->serialised()->create();
        $row = p51DispatchedAsset($this->store, $product);
        $container = p51KitContainer($this->store);

        try {
            (new PackContainerItem)($container, PackContainerItemData::from([
                'serialised_item_id' => $row->stock_level_id,
            ]));
            expect(false)->toBeTrue('Expected conflict');
        } catch (ContainerPackConflictException $e) {
            expect($e->conflict)->toBe('checked_out')
                ->and($e->details['opportunity_id'])->toBe($row->item?->opportunity_id)
                ->and($e->resolutions['auto_return_and_pack']['available'])->toBeTrue();
        }
    });

    it('auto-return-and-pack fires AssetReturned and records audit column', function () {
        $product = Product::factory()->serialised()->create();
        $row = p51DispatchedAsset($this->store, $product);
        $container = p51KitContainer($this->store);
        $eventsBefore = DB::table('verb_events')->count();

        $result = (new PackContainerItem)($container, PackContainerItemData::from([
            'serialised_item_id' => $row->stock_level_id,
            'resolve' => 'auto_return_and_pack',
        ]));

        expect($row->refresh()->status)->toBe(AssetAssignmentStatus::CheckedIn)
            ->and($result->container_item->auto_returned_from_opportunity_id)->toBe($row->item?->opportunity_id)
            ->and(DB::table('verb_events')->count())->toBeGreaterThan($eventsBefore);
    });

    it('requires pack_override permission for auto-return-and-pack', function () {
        $product = Product::factory()->serialised()->create();
        $row = p51DispatchedAsset($this->store, $product);
        $container = p51KitContainer($this->store);

        $operator = User::factory()->create();
        $operator->givePermissionTo(['containers.view', 'containers.pack', 'stock.access']);
        $this->actingAs($operator);

        expect(fn () => (new PackContainerItem)($container, PackContainerItemData::from([
            'serialised_item_id' => $row->stock_level_id,
            'resolve' => 'auto_return_and_pack',
        ])))->toThrow(AuthorizationException::class);
    });

    it('replays opportunity projections identically after auto-return-and-pack', function () {
        $product = Product::factory()->serialised()->create();
        $row = p51DispatchedAsset($this->store, $product);
        $container = p51KitContainer($this->store);

        (new PackContainerItem)($container, PackContainerItemData::from([
            'serialised_item_id' => $row->stock_level_id,
            'resolve' => 'auto_return_and_pack',
        ]));

        $assetBefore = OpportunityItemAsset::query()->whereKey($row->id)->firstOrFail();
        $snapshotBefore = [
            'id' => $assetBefore->id,
            'opportunity_item_id' => $assetBefore->opportunity_item_id,
            'stock_level_id' => $assetBefore->stock_level_id,
            'status' => $assetBefore->status,
            'returned_at' => $assetBefore->returned_at?->toIso8601String(),
        ];
        $eventCountBefore = DB::table('verb_events')->count();

        OpportunityItemAsset::query()->whereKey($row->id)->delete();

        Verbs::replay();

        $assetAfter = OpportunityItemAsset::query()->whereKey($row->id)->firstOrFail();
        $snapshotAfter = [
            'id' => $assetAfter->id,
            'opportunity_item_id' => $assetAfter->opportunity_item_id,
            'stock_level_id' => $assetAfter->stock_level_id,
            'status' => $assetAfter->status,
            'returned_at' => $assetAfter->returned_at?->toIso8601String(),
        ];

        expect($snapshotAfter)->toBe($snapshotBefore)
            ->and(DB::table('verb_events')->count())->toBe($eventCountBefore);
    });

    it('returns confirm_nesting conflict until confirmed', function () {
        $root = p51KitContainer($this->store);
        $child = p51KitContainer($this->store);

        try {
            (new PackContainerItem)($root, PackContainerItemData::from([
                'serialised_item_id' => $child->serialised_item_id,
            ]));
            expect(false)->toBeTrue('Expected nesting conflict');
        } catch (ContainerPackConflictException $e) {
            expect($e->conflict)->toBe('confirm_nesting')
                ->and($e->resolutions['confirm_nesting']['available'])->toBeTrue();
        }
    });

    it('includes sub-hire notice on successful pack without blocking', function () {
        $container = p51KitContainer($this->store);
        $product = Product::factory()->serialised()->create();
        $asset = StockLevel::factory()->serialised()->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
        ]);

        $supplier = Member::factory()->organisation()->create(['name' => 'Acme Subhire']);
        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'store_id' => $this->store->id,
        ]);

        VirtualStockIntake::factory()->confirmed()->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
            'purchase_order_id' => $po->id,
            'available_until' => now()->addWeek(),
        ]);

        $result = (new PackContainerItem)($container, PackContainerItemData::from([
            'serialised_item_id' => $asset->id,
        ]));

        expect($result->notices)->toHaveCount(1)
            ->and($result->notices[0]['type'])->toBe('sub_hire')
            ->and($result->notices[0]['supplier_name'])->toBe('Acme Subhire');
    });
});

describe('TransferContainerItem', function () {
    it('transfers atomically and auto-unseals a sealed source', function () {
        $source = p51KitContainer($this->store);
        $target = p51KitContainer($this->store);
        $asset = StockLevel::factory()->serialised()->create([
            'product_id' => Product::factory()->serialised()->create()->id,
            'store_id' => $this->store->id,
        ]);
        p51PackAsset($source, $asset);
        $source->update(['status' => ContainerStatus::Sealed->value, 'sealed_at' => now()]);

        (new TransferContainerItem)($target, TransferContainerItemData::from([
            'serialised_item_id' => $asset->id,
            'source_container_id' => $source->id,
        ]));

        expect($source->fresh()->status)->toBe(ContainerStatus::Open)
            ->and(ContainerItem::query()->where('container_id', $target->id)->whereNull('unpacked_at')->count())->toBe(1)
            ->and(ContainerItem::query()->where('container_id', $source->id)->whereNull('unpacked_at')->count())->toBe(0);
    });

    it('rolls back when the target pack fails mid-transfer', function () {
        $source = p51KitContainer($this->store);
        $target = p51KitContainer($this->store);
        $asset = StockLevel::factory()->serialised()->create([
            'product_id' => Product::factory()->serialised()->create()->id,
            'store_id' => $this->store->id,
        ]);
        p51PackAsset($source, $asset);

        $this->mock(PackContainerItem::class, function ($mock): void {
            $mock->shouldReceive('executePack')->andThrow(new RuntimeException('Simulated pack failure'));
        });

        expect(fn () => (new TransferContainerItem)($target, TransferContainerItemData::from([
            'serialised_item_id' => $asset->id,
            'source_container_id' => $source->id,
        ])))->toThrow(RuntimeException::class);

        expect(ContainerItem::query()
            ->where('container_id', $source->id)
            ->where('serialised_item_id', $asset->id)
            ->whereNull('unpacked_at')
            ->exists())->toBeTrue();
    });

    it('fires container.item.transferred audit event', function () {
        Event::fake([AuditableEvent::class]);

        $source = p51KitContainer($this->store);
        $target = p51KitContainer($this->store);
        $asset = StockLevel::factory()->serialised()->create([
            'product_id' => Product::factory()->serialised()->create()->id,
            'store_id' => $this->store->id,
        ]);
        p51PackAsset($source, $asset);

        (new TransferContainerItem)($target, TransferContainerItemData::from([
            'serialised_item_id' => $asset->id,
            'source_container_id' => $source->id,
        ]));

        Event::assertDispatched(
            AuditableEvent::class,
            fn (AuditableEvent $event): bool => $event->action === 'container.item.transferred',
        );
    });
});

describe('API pack conflicts', function () {
    it('returns 409 JSON shape for pack conflicts', function () {
        $owner = User::factory()->owner()->create();
        $token = $owner->createToken('test', ['containers:write'])->plainTextToken;
        $source = p51KitContainer($this->store);
        $target = p51KitContainer($this->store);
        $asset = StockLevel::factory()->serialised()->create([
            'product_id' => Product::factory()->serialised()->create()->id,
            'store_id' => $this->store->id,
        ]);
        p51PackAsset($source, $asset);

        $this->actingAs($owner)
            ->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/containers/{$target->uuid}/pack", [
                'serialised_item_id' => $asset->id,
            ])
            ->assertConflict()
            ->assertJsonStructure([
                'conflict',
                'details',
                'resolutions' => ['transfer' => ['available', 'requires_permission', 'description']],
            ]);
    });
});

describe('Livewire conflict dialog', function () {
    it('surfaces pack conflict resolutions on the show page', function () {
        $source = p51KitContainer($this->store);
        $target = p51KitContainer($this->store);
        $asset = StockLevel::factory()->serialised()->create([
            'product_id' => Product::factory()->serialised()->create()->id,
            'store_id' => $this->store->id,
        ]);
        p51PackAsset($source, $asset);

        Volt::test('containers.pack', ['container' => $target])
            ->set('scanInput', (string) $asset->id)
            ->call('scanItem')
            ->assertSet('packConflict.conflict', 'already_in_container')
            ->assertSee('Move item from current container to this container');
    });
});
