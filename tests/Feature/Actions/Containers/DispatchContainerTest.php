<?php

use App\Actions\Containers\DispatchContainer;
use App\Actions\Containers\PackContainerItem;
use App\Actions\Containers\SealContainer;
use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\ConvertToOrder;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Contracts\Availability\AvailabilityResolutionProvider;
use App\Data\Containers\ContainerDispatchAmendmentData;
use App\Data\Containers\DispatchContainerData;
use App\Data\Containers\PackContainerItemData;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Enums\AssetAssignmentStatus;
use App\Enums\AvailabilityResolution;
use App\Enums\ContainerAvailabilityMode;
use App\Enums\ContainerDissolveReason;
use App\Enums\ContainerStatus;
use App\Enums\DemandPhase;
use App\Enums\LineItemTransactionType;
use App\Enums\ShortageDispatchPolicy;
use App\Enums\ShortagePolicy;
use App\Exceptions\Containers\ContainerDispatchAmendmentRequiredException;
use App\Models\Container;
use App\Models\Demand;
use App\Models\MemberStore;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\OpportunityItemAsset;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
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

function p52TransportContainer(Store $store, ?int $parentId = null, int $maxDepth = 2): Container
{
    $product = Product::factory()->containerable(ContainerAvailabilityMode::Transport)->create([
        'container_max_nesting_depth' => $maxDepth,
    ]);
    $housing = StockLevel::factory()->serialised()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
    ]);

    return Container::factory()->transport()->create([
        'product_id' => $product->id,
        'serialised_item_id' => $housing->id,
        'store_id' => $store->id,
        'parent_container_id' => $parentId,
    ]);
}

function p52PackAsset(Container $container, StockLevel $asset, ?string $resolve = null): void
{
    $payload = ['serialised_item_id' => $asset->id];

    if ($resolve !== null) {
        $payload['resolve'] = $resolve;
    }

    (new PackContainerItem)($container, PackContainerItemData::from($payload));
}

function p52Seal(Container $container): void
{
    (new SealContainer)($container);
}

function p52GrantStoreAccess(User $user, Store $store): void
{
    MemberStore::create([
        'member_id' => $user->member_id,
        'store_id' => $store->id,
        'created_at' => now(),
    ]);
}

/**
 * @param  array<int, string>  $lineQuantities  product_id => quantity
 */
function p52DispatchOrder(Store $store, array $lineQuantities): Opportunity
{
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Container dispatch job',
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

describe('DispatchContainer happy path', function () {
    it('dispatches a sealed transport case with nested depth 2 and dissolves the tree', function () {
        $componentProduct = Product::factory()->serialised()->create();
        $outer = p52TransportContainer($this->store, maxDepth: 2);
        $inner = p52TransportContainer($this->store, maxDepth: 2);
        $inner->update(['parent_container_id' => $outer->id]);

        $innerAsset = StockLevel::factory()->serialised()->create([
            'product_id' => $componentProduct->id,
            'store_id' => $this->store->id,
        ]);
        $innerHousing = StockLevel::query()->whereKey($inner->serialised_item_id)->firstOrFail();
        p52PackAsset($inner, $innerAsset);
        p52Seal($inner);

        p52PackAsset($outer, $innerHousing, 'confirm_nesting');
        p52PackAsset($outer, StockLevel::factory()->serialised()->create([
            'product_id' => $componentProduct->id,
            'store_id' => $this->store->id,
        ]));
        p52Seal($outer);

        $opportunity = p52DispatchOrder($this->store, [
            $componentProduct->id => '2',
            $outer->product_id => '1',
            $inner->product_id => '1',
        ]);

        $eventsBefore = verbEventCount();

        $result = (new DispatchContainer)($outer, DispatchContainerData::from([
            'opportunity_id' => $opportunity->id,
        ]));

        expect($result->assets_checked_out)->toBeGreaterThan(0)
            ->and($result->containers_dissolved)->toBe(2)
            ->and($outer->fresh()->status)->toBe(ContainerStatus::Dissolved)
            ->and($outer->fresh()->dissolved_reason)->toBe(ContainerDissolveReason::Dispatched->value)
            ->and($inner->fresh()->status)->toBe(ContainerStatus::Dissolved)
            ->and(OpportunityItemAsset::query()->whereHas('item', fn ($q) => $q->where('opportunity_id', $opportunity->id))->count())
            ->toBeGreaterThan(0);

        $dispatched = OpportunityItemAsset::query()
            ->whereHas('item', fn ($q) => $q->where('opportunity_id', $opportunity->id))
            ->get();

        expect($dispatched->every(fn (OpportunityItemAsset $row): bool => $row->status === AssetAssignmentStatus::Dispatched))->toBeTrue();

        expect(verbEventCount())->toBeGreaterThan($eventsBefore);
    });

    it('fires exactly the expected verb events without duplicates', function () {
        $component = Product::factory()->serialised()->create();
        $container = p52TransportContainer($this->store);
        p52PackAsset($container, StockLevel::factory()->serialised()->create([
            'product_id' => $component->id,
            'store_id' => $this->store->id,
        ]));
        p52Seal($container);

        $opportunity = p52DispatchOrder($this->store, [
            $component->id => '1',
            $container->product_id => '1',
        ]);

        $eventsBefore = verbEventCount();

        (new DispatchContainer)($container, DispatchContainerData::from([
            'opportunity_id' => $opportunity->id,
        ]));

        // Housing: allocate + dispatch; component: allocate + dispatch + container-set.
        expect(verbEventCount() - $eventsBefore)->toBe(5);
    });
});

describe('DispatchContainer guards', function () {
    it('rejects unsealed containers', function () {
        $container = p52TransportContainer($this->store);
        p52PackAsset($container, StockLevel::factory()->serialised()->create([
            'product_id' => Product::factory()->serialised()->create()->id,
            'store_id' => $this->store->id,
        ]));
        $opportunity = p52DispatchOrder($this->store, [$container->product_id => '1']);

        expect(fn () => (new DispatchContainer)($container, DispatchContainerData::from([
            'opportunity_id' => $opportunity->id,
        ])))->toThrow(ValidationException::class);
    });

    it('rejects quotation-state opportunities', function () {
        $container = p52TransportContainer($this->store);
        $product = Product::factory()->serialised()->create();
        p52PackAsset($container, StockLevel::factory()->serialised()->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
        ]));
        p52Seal($container);

        $created = (new CreateOpportunity)(CreateOpportunityData::from([
            'subject' => 'Quote only',
            'store_id' => $this->store->id,
        ]));
        $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();
        (new ConvertToQuotation)($opportunity);
        (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
            'name' => $product->name,
            'itemable_id' => $product->id,
            'itemable_type' => Product::class,
            'quantity' => '1',
        ]));

        expect(fn () => (new DispatchContainer)($container, DispatchContainerData::from([
            'opportunity_id' => $opportunity->id,
        ])))->toThrow(ValidationException::class);
    });
});

describe('DispatchContainer amendment matrix', function () {
    it('auto-adds warehouse-addable products with default charge', function () {
        $component = Product::factory()->serialised()->create([
            'is_warehouse_addable' => true,
            'warehouse_add_default_charge' => 0,
        ]);
        $container = p52TransportContainer($this->store);
        p52PackAsset($container, StockLevel::factory()->serialised()->create([
            'product_id' => $component->id,
            'store_id' => $this->store->id,
        ]));
        p52Seal($container);

        $opportunity = p52DispatchOrder($this->store, [
            $container->product_id => '1',
        ]);

        $result = (new DispatchContainer)($container, DispatchContainerData::from([
            'opportunity_id' => $opportunity->id,
        ]));

        expect($result->auto_added_products)->toHaveCount(1)
            ->and($result->auto_added_products[0]['product_id'])->toBe($component->id)
            ->and(OpportunityItem::query()
                ->where('opportunity_id', $opportunity->id)
                ->where('itemable_id', $component->id)
                ->exists())->toBeTrue();
    });

    it('returns 409 prompt shape when dispatch_add_items is required', function () {
        $missing = Product::factory()->serialised()->create(['is_warehouse_addable' => false]);
        $container = p52TransportContainer($this->store);
        p52PackAsset($container, StockLevel::factory()->serialised()->create([
            'product_id' => $missing->id,
            'store_id' => $this->store->id,
        ]));
        p52Seal($container);

        $opportunity = p52DispatchOrder($this->store, [
            $container->product_id => '1',
        ]);

        $operator = User::factory()->create();
        $operator->givePermissionTo([
            'containers.view',
            'opportunities.edit',
            'opportunities.dispatch_add_items',
            'opportunities.create',
            'stock.access',
        ]);
        p52GrantStoreAccess($operator, $this->store);
        $this->actingAs($operator);

        try {
            (new DispatchContainer)($container, DispatchContainerData::from([
                'opportunity_id' => $opportunity->id,
            ]));
            $this->fail('Expected amendment prompt.');
        } catch (ContainerDispatchAmendmentRequiredException $e) {
            expect($e->payload())->toHaveKeys(['message', 'amendments', 'validation'])
                ->and($e->payload()['amendments'][0]['product_id'])->toBe($missing->id)
                ->and($e->payload()['amendments'][0]['verdict'])->toBe('prompt');
        }
    });

    it('executes approved amendments on re-post', function () {
        $missing = Product::factory()->serialised()->create(['is_warehouse_addable' => false]);
        $container = p52TransportContainer($this->store);
        p52PackAsset($container, StockLevel::factory()->serialised()->create([
            'product_id' => $missing->id,
            'store_id' => $this->store->id,
        ]));
        p52Seal($container);

        $opportunity = p52DispatchOrder($this->store, [
            $container->product_id => '1',
        ]);

        $operator = User::factory()->create();
        $operator->givePermissionTo([
            'containers.view',
            'opportunities.edit',
            'opportunities.dispatch_add_items',
            'opportunities.create',
            'stock.access',
        ]);
        p52GrantStoreAccess($operator, $this->store);
        $this->actingAs($operator);

        $result = (new DispatchContainer)($container, DispatchContainerData::from([
            'opportunity_id' => $opportunity->id,
            'amendments' => [
                ContainerDispatchAmendmentData::from([
                    'product_id' => $missing->id,
                    'action' => 'add',
                    'quantity' => '1',
                    'price' => 0,
                ]),
            ],
        ]));

        expect($result->amended_products)->toHaveCount(1)
            ->and($container->fresh()->status)->toBe(ContainerStatus::Dissolved);
    });

    it('fails with 422 when missing products cannot be resolved', function () {
        $missing = Product::factory()->serialised()->create(['is_warehouse_addable' => false]);
        $container = p52TransportContainer($this->store);
        p52PackAsset($container, StockLevel::factory()->serialised()->create([
            'product_id' => $missing->id,
            'store_id' => $this->store->id,
        ]));
        p52Seal($container);

        $opportunity = p52DispatchOrder($this->store, [
            $container->product_id => '1',
        ]);

        $operator = User::factory()->create();
        $operator->givePermissionTo(['containers.view', 'opportunities.edit', 'opportunities.create', 'stock.access']);
        p52GrantStoreAccess($operator, $this->store);
        $this->actingAs($operator);

        expect(fn () => (new DispatchContainer)($container, DispatchContainerData::from([
            'opportunity_id' => $opportunity->id,
        ])))->toThrow(ValidationException::class);
    });
});

describe('DispatchContainer shortage gate', function () {
    it('rolls back everything when the dispatch gate blocks', function () {
        $this->store->update([
            'shortage_policy' => ShortagePolicy::Allow->value,
            'shortage_dispatch_policy' => ShortageDispatchPolicy::Block->value,
        ]);

        $component = Product::factory()->serialised()->create();
        $asset = StockLevel::factory()->serialised()->create([
            'product_id' => $component->id,
            'store_id' => $this->store->id,
        ]);

        Demand::factory()
            ->phase(DemandPhase::Committed)
            ->window(Carbon::parse('2026-09-01T09:00:00Z'), Carbon::parse('2026-09-05T17:00:00Z'))
            ->create([
                'product_id' => $component->id,
                'store_id' => $this->store->id,
                'quantity' => 1,
                'source_type' => 'opportunity_item',
                'source_id' => 990001,
                'metadata' => [],
            ]);

        $container = p52TransportContainer($this->store);
        p52PackAsset($container, $asset);
        p52Seal($container);

        $opportunity = p52DispatchOrder($this->store, [
            $container->product_id => '1',
            $component->id => '1',
        ]);

        $itemsBefore = OpportunityItem::query()->where('opportunity_id', $opportunity->id)->count();

        expect(fn () => (new DispatchContainer)($container, DispatchContainerData::from([
            'opportunity_id' => $opportunity->id,
        ])))->toThrow(ValidationException::class);

        expect(OpportunityItemAsset::query()->whereHas('item', fn ($q) => $q->where('opportunity_id', $opportunity->id))->count())->toBe(0)
            ->and(OpportunityItem::query()->where('opportunity_id', $opportunity->id)->count())->toBe($itemsBefore)
            ->and($container->fresh()->status)->toBe(ContainerStatus::Sealed);
    });

    it('proceeds under a WarnPartial shortage policy', function () {
        $this->store->update([
            'shortage_policy' => ShortagePolicy::Allow->value,
            'shortage_dispatch_policy' => ShortageDispatchPolicy::WarnPartial->value,
        ]);

        $component = Product::factory()->serialised()->create();
        $asset = StockLevel::factory()->serialised()->create([
            'product_id' => $component->id,
            'store_id' => $this->store->id,
        ]);

        Demand::factory()
            ->phase(DemandPhase::Committed)
            ->window(Carbon::parse('2026-09-01T09:00:00Z'), Carbon::parse('2026-09-05T17:00:00Z'))
            ->create([
                'product_id' => $component->id,
                'store_id' => $this->store->id,
                'quantity' => 1,
                'source_type' => 'opportunity_item',
                'source_id' => 990002,
                'metadata' => [],
            ]);

        $container = p52TransportContainer($this->store);
        p52PackAsset($container, $asset);
        p52Seal($container);

        $opportunity = p52DispatchOrder($this->store, [
            $container->product_id => '1',
            $component->id => '1',
        ]);

        $action = new DispatchContainer;
        $result = $action($container, DispatchContainerData::from([
            'opportunity_id' => $opportunity->id,
        ]));

        expect($result->assets_checked_out)->toBeGreaterThan(0)
            ->and($container->fresh()->status)->toBe(ContainerStatus::Dissolved)
            ->and($action->gateResult)->not->toBeNull()
            ->and($action->gateResult->warned())->toBeTrue();
    });
});

describe('DispatchContainer replay', function () {
    it('rebuilds identical opportunity item asset rows and does not resurrect containers', function () {
        $component = Product::factory()->serialised()->create();
        $container = p52TransportContainer($this->store);
        $asset = StockLevel::factory()->serialised()->create([
            'product_id' => $component->id,
            'store_id' => $this->store->id,
        ]);
        p52PackAsset($container, $asset);
        p52Seal($container);

        $opportunity = p52DispatchOrder($this->store, [
            $component->id => '1',
            $container->product_id => '1',
        ]);

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
            ->and($container->fresh()->status)->toBe($containerStatusBefore)
            ->and(verbEventCount())->toBe($eventCountBeforeReplay);
    });
});

it('requires dispatch permission via policy', function () {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo('containers.view');
    $this->actingAs($viewer);

    $container = p52TransportContainer($this->store);

    expect(fn () => (new DispatchContainer)($container, DispatchContainerData::from([
        'opportunity_id' => 1,
    ])))->toThrow(AuthorizationException::class);
});

it('smokes the volt dispatch affordance', function () {
    $component = Product::factory()->serialised()->create();
    $container = p52TransportContainer($this->store);
    p52PackAsset($container, StockLevel::factory()->serialised()->create([
        'product_id' => $component->id,
        'store_id' => $this->store->id,
    ]));
    p52Seal($container);

    $opportunity = p52DispatchOrder($this->store, [
        $component->id => '1',
        $container->product_id => '1',
    ]);

    Volt::test('containers.show', ['container' => $container->fresh()])
        ->assertSee('Dispatch to opportunity')
        ->set('dispatchOpportunityId', (string) $opportunity->id)
        ->call('dispatchContainer')
        ->assertHasNoErrors();

    expect($container->fresh()->status)->toBe(ContainerStatus::Dissolved);
});

it('surfaces dispatch amendments in the opportunity assets book-out scan', function () {
    $missing = Product::factory()->serialised()->create(['is_warehouse_addable' => false]);
    $container = p52TransportContainer($this->store);
    p52PackAsset($container, StockLevel::factory()->serialised()->create([
        'product_id' => $missing->id,
        'store_id' => $this->store->id,
    ]));
    p52Seal($container);
    $container->update(['barcode' => 'CASE-AMEND-SCAN']);

    $opportunity = p52DispatchOrder($this->store, [
        $container->product_id => '1',
    ]);

    $operator = User::factory()->create();
    $operator->givePermissionTo([
        'containers.view',
        'opportunities.view',
        'opportunities.edit',
        'opportunities.dispatch_add_items',
        'opportunities.create',
        'stock.access',
    ]);
    p52GrantStoreAccess($operator, $this->store);
    $this->actingAs($operator);

    Volt::test('opportunities.assets', ['opportunity' => $opportunity])
        ->set('subTab', 'book_out')
        ->set('scanAsset', 'CASE-AMEND-SCAN')
        ->call('scanBookOut')
        ->assertSet('containerDispatchPrompt.message', fn (?string $message): bool => is_string($message) && $message !== '')
        ->assertSee('Container dispatch amendments')
        ->assertSee('Apply amendments and dispatch');
});
