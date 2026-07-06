<?php

use App\Actions\Containers\DispatchContainer;
use App\Actions\Containers\PackContainerItem;
use App\Actions\Containers\ReturnKitContainer;
use App\Actions\Containers\SealContainer;
use App\Actions\Containers\VerifyKitCheckin;
use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\ConvertToOrder;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\ReturnAsset;
use App\Contracts\Availability\AvailabilityResolutionProvider;
use App\Data\Containers\DispatchContainerData;
use App\Data\Containers\PackContainerItemData;
use App\Data\Containers\ReturnKitContainerData;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\ReturnAssetData;
use App\Enums\AssetAssignmentStatus;
use App\Enums\AvailabilityResolution;
use App\Enums\ContainerAvailabilityMode;
use App\Enums\ContainerCheckinMode;
use App\Enums\ContainerStatus;
use App\Enums\DemandPhase;
use App\Enums\LineItemTransactionType;
use App\Enums\OpportunityStatus;
use App\Events\AuditableEvent;
use App\Jobs\DeliverWebhook;
use App\Models\Container;
use App\Models\ContainerItem;
use App\Models\Demand;
use App\Models\MemberStore;
use App\Models\Opportunity;
use App\Models\OpportunityItemAsset;
use App\Models\Product;
use App\Models\SerialisedComponent;
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

function p53GrantStoreAccess(User $user, Store $store): void
{
    MemberStore::create([
        'member_id' => $user->member_id,
        'store_id' => $store->id,
        'created_at' => now(),
    ]);
}

function p53KitContainer(Store $store, ?ContainerCheckinMode $checkinMode = null): Container
{
    $product = Product::factory()->containerable(ContainerAvailabilityMode::Kit)->create([
        'container_checkin_mode' => ($checkinMode ?? ContainerCheckinMode::Parent)->value,
    ]);
    $housing = StockLevel::factory()->serialised()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
    ]);

    return Container::factory()->kit()->create([
        'product_id' => $product->id,
        'serialised_item_id' => $housing->id,
        'store_id' => $store->id,
    ]);
}

function p53HybridContainer(Store $store, Product $fixedProduct, Product $poolProduct): Container
{
    $kitProduct = Product::factory()->containerable(ContainerAvailabilityMode::Hybrid)->create([
        'container_checkin_mode' => ContainerCheckinMode::Parent->value,
    ]);

    SerialisedComponent::factory()->fixed()->quantity(1)->create([
        'product_id' => $kitProduct->id,
        'component_product_id' => $fixedProduct->id,
    ]);
    SerialisedComponent::factory()->pool()->quantity(1)->create([
        'product_id' => $kitProduct->id,
        'component_product_id' => $poolProduct->id,
    ]);

    $housing = StockLevel::factory()->serialised()->create([
        'product_id' => $kitProduct->id,
        'store_id' => $store->id,
    ]);

    return Container::factory()->hybrid()->create([
        'product_id' => $kitProduct->id,
        'serialised_item_id' => $housing->id,
        'store_id' => $store->id,
    ]);
}

function p53PackAsset(Container $container, StockLevel $asset): void
{
    (new PackContainerItem)($container, PackContainerItemData::from([
        'serialised_item_id' => $asset->id,
    ]));
}

function p53Seal(Container $container): void
{
    (new SealContainer)($container);
}

/**
 * @param  array<int, string>  $lineQuantities
 */
function p53DispatchOrder(Store $store, array $lineQuantities): Opportunity
{
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Kit dispatch job',
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

describe('DispatchKitContainer happy path', function () {
    it('dispatches housing and contents, persists container and demands', function () {
        $fixed = Product::factory()->serialised()->create();
        $container = p53KitContainer($this->store);
        $fixedAsset = StockLevel::factory()->serialised()->create([
            'product_id' => $fixed->id,
            'store_id' => $this->store->id,
        ]);
        p53PackAsset($container, $fixedAsset);
        p53Seal($container);

        $demandBefore = Demand::query()
            ->where('source_type', 'container')
            ->where('phase', DemandPhase::Committed->value)
            ->count();

        $opportunity = p53DispatchOrder($this->store, [
            $container->product_id => '1',
        ]);

        $result = (new DispatchContainer)($container, DispatchContainerData::from([
            'opportunity_id' => $opportunity->id,
        ]));

        expect($result->containers_dissolved)->toBe(0)
            ->and($container->fresh()->status)->toBe(ContainerStatus::Dispatched)
            ->and(ContainerItem::query()->where('container_id', $container->id)->whereNull('unpacked_at')->count())->toBe(1)
            ->and($container->fresh()->dissolved_at)->toBeNull()
            ->and($result->assets_checked_out)->toBe(2);

        $assignments = OpportunityItemAsset::query()
            ->whereHas('item', fn ($q) => $q->where('opportunity_id', $opportunity->id))
            ->get();

        expect($assignments)->toHaveCount(2)
            ->and($assignments->every(fn (OpportunityItemAsset $row): bool => $row->status === AssetAssignmentStatus::Dispatched))->toBeTrue();

        expect(Demand::query()
            ->where('source_type', 'container')
            ->where('phase', DemandPhase::Committed->value)
            ->count())->toBeGreaterThanOrEqual($demandBefore);
    });

    it('dispatches hybrid kits with pool lines on separate opportunity items', function () {
        $fixed = Product::factory()->serialised()->create();
        $pool = Product::factory()->serialised()->create();
        $container = p53HybridContainer($this->store, $fixed, $pool);

        p53PackAsset($container, StockLevel::factory()->serialised()->create([
            'product_id' => $fixed->id,
            'store_id' => $this->store->id,
        ]));
        p53PackAsset($container, StockLevel::factory()->serialised()->create([
            'product_id' => $pool->id,
            'store_id' => $this->store->id,
        ]));
        p53Seal($container);

        $opportunity = p53DispatchOrder($this->store, [
            $container->product_id => '1',
            $pool->id => '1',
        ]);

        (new DispatchContainer)($container, DispatchContainerData::from([
            'opportunity_id' => $opportunity->id,
        ]));

        $kitLineIds = $opportunity->items()
            ->where('itemable_id', $container->product_id)
            ->pluck('id');
        $poolLineIds = $opportunity->items()
            ->where('itemable_id', $pool->id)
            ->pluck('id');

        $kitAssignments = OpportunityItemAsset::query()->whereIn('opportunity_item_id', $kitLineIds)->count();
        $poolAssignments = OpportunityItemAsset::query()->whereIn('opportunity_item_id', $poolLineIds)->count();

        expect($kitAssignments)->toBe(1)
            ->and($poolAssignments)->toBe(1)
            ->and($container->fresh()->status)->toBe(ContainerStatus::Dispatched);
    });
});

describe('DispatchKitContainer guards', function () {
    it('rejects unsealed kits', function () {
        $container = p53KitContainer($this->store);
        $opportunity = p53DispatchOrder($this->store, [$container->product_id => '1']);

        expect(fn () => (new DispatchContainer)($container, DispatchContainerData::from([
            'opportunity_id' => $opportunity->id,
        ])))->toThrow(ValidationException::class);
    });

    it('routes transport containers through dissolve path', function () {
        $product = Product::factory()->containerable(ContainerAvailabilityMode::Transport)->create();
        $housing = StockLevel::factory()->serialised()->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
        ]);
        $container = Container::factory()->transport()->create([
            'product_id' => $product->id,
            'serialised_item_id' => $housing->id,
            'store_id' => $this->store->id,
        ]);

        expect($container->availabilityMode())->toBe(ContainerAvailabilityMode::Transport);
    });

    it('fails when kit product is missing from opportunity', function () {
        $container = p53KitContainer($this->store);
        Product::query()->whereKey($container->product_id)->update(['is_warehouse_addable' => false]);
        p53PackAsset($container, StockLevel::factory()->serialised()->create([
            'product_id' => Product::factory()->serialised()->create()->id,
            'store_id' => $this->store->id,
        ]));
        p53Seal($container);

        $opportunity = p53DispatchOrder($this->store, [
            Product::factory()->serialised()->create()->id => '1',
        ]);

        $operator = User::factory()->create();
        $operator->givePermissionTo(['containers.view', 'opportunities.edit', 'opportunities.create', 'stock.access']);
        p53GrantStoreAccess($operator, $this->store);
        $this->actingAs($operator);

        expect(fn () => (new DispatchContainer)($container, DispatchContainerData::from([
            'opportunity_id' => $opportunity->id,
        ])))->toThrow(ValidationException::class);
    });
});

describe('ReturnKitContainer check-in modes', function () {
    it('returns all assets in parent mode', function () {
        $fixed = Product::factory()->serialised()->create();
        $container = p53KitContainer($this->store, ContainerCheckinMode::Parent);
        $fixedAsset = StockLevel::factory()->serialised()->create([
            'product_id' => $fixed->id,
            'store_id' => $this->store->id,
        ]);
        p53PackAsset($container, $fixedAsset);
        p53Seal($container);
        $opportunity = p53DispatchOrder($this->store, [$container->product_id => '1']);
        (new DispatchContainer)($container, DispatchContainerData::from(['opportunity_id' => $opportunity->id]));
        $container = $container->fresh();

        $result = (new ReturnKitContainer)($container, ReturnKitContainerData::from([]));

        expect($result->assets_returned)->toBe(2)
            ->and($container->fresh()->status)->toBe(ContainerStatus::Returned)
            ->and($result->verification_required)->toBeFalse()
            ->and(OpportunityItemAsset::query()
                ->whereHas('item', fn ($q) => $q->where('opportunity_id', $opportunity->id))
                ->where('status', AssetAssignmentStatus::CheckedIn)
                ->count())->toBe(2);
    });

    it('rejects whole-kit return in individual mode', function () {
        $fixed = Product::factory()->serialised()->create();
        $container = p53KitContainer($this->store, ContainerCheckinMode::Individual);
        p53PackAsset($container, StockLevel::factory()->serialised()->create([
            'product_id' => $fixed->id,
            'store_id' => $this->store->id,
        ]));
        p53Seal($container);
        $opportunity = p53DispatchOrder($this->store, [$container->product_id => '1']);
        (new DispatchContainer)($container, DispatchContainerData::from(['opportunity_id' => $opportunity->id]));

        expect(fn () => (new ReturnKitContainer)($container->fresh(), ReturnKitContainerData::from([])))
            ->toThrow(ValidationException::class);
    });

    it('auto-completes kit return after last individual asset return', function () {
        $fixed = Product::factory()->serialised()->create();
        $container = p53KitContainer($this->store, ContainerCheckinMode::Individual);
        $fixedAsset = StockLevel::factory()->serialised()->create([
            'product_id' => $fixed->id,
            'store_id' => $this->store->id,
        ]);
        p53PackAsset($container, $fixedAsset);
        p53Seal($container);
        $opportunity = p53DispatchOrder($this->store, [$container->product_id => '1']);
        (new DispatchContainer)($container, DispatchContainerData::from(['opportunity_id' => $opportunity->id]));
        $container = $container->fresh();
        $housingId = (int) $container->serialised_item_id;

        $contentAssignment = OpportunityItemAsset::query()
            ->where('stock_level_id', $fixedAsset->id)
            ->firstOrFail();
        $housingAssignment = OpportunityItemAsset::query()
            ->where('stock_level_id', $housingId)
            ->firstOrFail();

        (new ReturnAsset)($contentAssignment->refresh(), ReturnAssetData::from([]));
        expect($container->fresh()->status)->toBe(ContainerStatus::Dispatched);

        (new ReturnAsset)($housingAssignment->refresh(), ReturnAssetData::from([]));
        expect($container->fresh()->status)->toBe(ContainerStatus::Returned);
    });

    it('flags parent_then_verify returns and clears via verify action', function () {
        $container = p53KitContainer($this->store, ContainerCheckinMode::ParentThenVerify);
        p53PackAsset($container, StockLevel::factory()->serialised()->create([
            'product_id' => Product::factory()->serialised()->create()->id,
            'store_id' => $this->store->id,
        ]));
        p53Seal($container);
        $opportunity = p53DispatchOrder($this->store, [$container->product_id => '1']);
        (new DispatchContainer)($container, DispatchContainerData::from(['opportunity_id' => $opportunity->id]));

        $result = (new ReturnKitContainer)($container->fresh(), ReturnKitContainerData::from([]));
        expect($result->verification_required)->toBeTrue()
            ->and($container->fresh()->checkin_verification_required_at)->not->toBeNull();

        (new VerifyKitCheckin)($container->fresh());
        expect($container->fresh()->checkin_verification_required_at)->toBeNull();
    });
});

describe('DispatchKitContainer replay and events', function () {
    it('replays opportunity assets without resurrecting container state', function () {
        $fixed = Product::factory()->serialised()->create();
        $container = p53KitContainer($this->store);
        p53PackAsset($container, StockLevel::factory()->serialised()->create([
            'product_id' => $fixed->id,
            'store_id' => $this->store->id,
        ]));
        p53Seal($container);

        $opportunity = p53DispatchOrder($this->store, [$container->product_id => '1']);

        (new DispatchContainer)($container, DispatchContainerData::from([
            'opportunity_id' => $opportunity->id,
        ]));

        $rowsBefore = OpportunityItemAsset::query()
            ->whereHas('item', fn ($q) => $q->where('opportunity_id', $opportunity->id))
            ->orderBy('id')
            ->get()
            ->map(fn (OpportunityItemAsset $row): array => $row->only([
                'id', 'opportunity_item_id', 'stock_level_id', 'status', 'container_stock_level_id',
            ]))
            ->all();

        $containerStatusBefore = $container->fresh()->status;
        $eventCountBeforeReplay = verbEventCount();

        OpportunityItemAsset::query()->delete();
        StockLevel::query()->update(['quantity_allocated' => 0]);
        $container->forceFill(['status' => ContainerStatus::Sealed->value, 'dispatched_at' => null, 'opportunity_id' => null])->saveQuietly();

        Verbs::replay();

        $rowsAfter = OpportunityItemAsset::query()
            ->whereHas('item', fn ($q) => $q->where('opportunity_id', $opportunity->id))
            ->orderBy('id')
            ->get()
            ->map(fn (OpportunityItemAsset $row): array => $row->only([
                'id', 'opportunity_item_id', 'stock_level_id', 'status', 'container_stock_level_id',
            ]))
            ->all();

        expect($rowsAfter)->toBe($rowsBefore)
            ->and($container->fresh()->status)->toBe(ContainerStatus::Sealed)
            ->and(verbEventCount())->toBe($eventCountBeforeReplay);
    });

    it('fires container.dispatched audit and webhook parity', function () {
        Event::fake([AuditableEvent::class]);

        Webhook::factory()->create([
            'user_id' => auth()->id(),
            'events' => ['container.dispatched'],
            'is_active' => true,
        ]);

        $container = p53KitContainer($this->store);
        p53PackAsset($container, StockLevel::factory()->serialised()->create([
            'product_id' => Product::factory()->serialised()->create()->id,
            'store_id' => $this->store->id,
        ]));
        p53Seal($container);

        $opportunity = p53DispatchOrder($this->store, [$container->product_id => '1']);

        (new DispatchContainer)($container, DispatchContainerData::from([
            'opportunity_id' => $opportunity->id,
        ]));

        Event::assertDispatched(
            AuditableEvent::class,
            fn (AuditableEvent $event): bool => $event->action === 'container.dispatched',
        );

        Queue::assertPushed(
            DeliverWebhook::class,
            fn (DeliverWebhook $job): bool => $job->event === 'container.dispatched',
        );
    });

    it('fires container.returned audit and webhook parity', function () {
        Event::fake([AuditableEvent::class]);

        Webhook::factory()->create([
            'user_id' => auth()->id(),
            'events' => ['container.returned'],
            'is_active' => true,
        ]);

        $container = p53KitContainer($this->store);
        p53PackAsset($container, StockLevel::factory()->serialised()->create([
            'product_id' => Product::factory()->serialised()->create()->id,
            'store_id' => $this->store->id,
        ]));
        p53Seal($container);

        $opportunity = p53DispatchOrder($this->store, [$container->product_id => '1']);
        (new DispatchContainer)($container, DispatchContainerData::from(['opportunity_id' => $opportunity->id]));
        (new ReturnKitContainer)($container->fresh(), ReturnKitContainerData::from([]));

        Event::assertDispatched(
            AuditableEvent::class,
            fn (AuditableEvent $event): bool => $event->action === 'container.returned',
        );

        Queue::assertPushed(
            DeliverWebhook::class,
            fn (DeliverWebhook $job): bool => $job->event === 'container.returned',
        );
    });
});

it('requires return permission via policy', function () {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo('containers.view');
    $this->actingAs($viewer);

    $container = p53KitContainer($this->store);
    $container->forceFill(['status' => ContainerStatus::Dispatched->value])->saveQuietly();

    expect(fn () => (new ReturnKitContainer)($container, ReturnKitContainerData::from([])))
        ->toThrow(AuthorizationException::class);
});

it('promotes opportunity status after kit dispatch', function () {
    $container = p53KitContainer($this->store);
    p53PackAsset($container, StockLevel::factory()->serialised()->create([
        'product_id' => Product::factory()->serialised()->create()->id,
        'store_id' => $this->store->id,
    ]));
    p53Seal($container);

    $opportunity = p53DispatchOrder($this->store, [$container->product_id => '1']);

    (new DispatchContainer)($container, DispatchContainerData::from([
        'opportunity_id' => $opportunity->id,
    ]));

    expect($opportunity->fresh()->statusEnum())->toBe(OpportunityStatus::OrderOnHire);
});

it('smokes volt kit dispatch and return affordances', function () {
    $container = p53KitContainer($this->store);
    p53PackAsset($container, StockLevel::factory()->serialised()->create([
        'product_id' => Product::factory()->serialised()->create()->id,
        'store_id' => $this->store->id,
    ]));
    p53Seal($container);

    $opportunity = p53DispatchOrder($this->store, [$container->product_id => '1']);

    Volt::test('containers.show', ['container' => $container->fresh()])
        ->assertSee('Check-in mode')
        ->set('dispatchOpportunityId', (string) $opportunity->id)
        ->call('dispatchContainer')
        ->assertHasNoErrors();

    $container = $container->fresh();
    expect($container->status)->toBe(ContainerStatus::Dispatched);

    Volt::test('containers.show', ['container' => $container])
        ->assertSee('Return kit')
        ->call('returnKit')
        ->assertHasNoErrors();

    expect($container->fresh()->status)->toBe(ContainerStatus::Returned);
});
