<?php

use App\Actions\Containers\PackContainerItem;
use App\Actions\Containers\SealContainer;
use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\ConvertToOrder;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Contracts\Availability\AvailabilityResolutionProvider;
use App\Data\Containers\PackContainerItemData;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Enums\AvailabilityResolution;
use App\Enums\ContainerAvailabilityMode;
use App\Enums\ContainerStatus;
use App\Enums\LineItemTransactionType;
use App\Models\Container;
use App\Models\MemberStore;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
    $this->actingAs($this->owner);

    $this->app->bind(AvailabilityResolutionProvider::class, fn () => new class implements AvailabilityResolutionProvider
    {
        public function resolve(): AvailabilityResolution
        {
            return AvailabilityResolution::Daily;
        }
    });

    $this->store = Store::factory()->create(['timezone' => 'UTC']);
    $this->token = $this->owner->createToken('test', [
        'containers:read',
        'containers:write',
        'opportunities:read',
        'opportunities:write',
    ])->plainTextToken;
});

function apiTransportContainer(Store $store): Container
{
    $product = Product::factory()->containerable(ContainerAvailabilityMode::Transport)->create();
    $housing = StockLevel::factory()->serialised()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
    ]);

    return Container::factory()->transport()->create([
        'product_id' => $product->id,
        'serialised_item_id' => $housing->id,
        'store_id' => $store->id,
    ]);
}

function apiPackAndSeal(Container $container, Store $store, Product $product): void
{
    $asset = StockLevel::factory()->serialised()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
    ]);

    (new PackContainerItem)($container, PackContainerItemData::from([
        'serialised_item_id' => $asset->id,
    ]));
    (new SealContainer)($container);
}

/**
 * @param  array<int, string>  $lineQuantities  product_id => quantity
 */
function apiDispatchOrder(Store $store, array $lineQuantities): Opportunity
{
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'API container dispatch',
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

describe('POST /api/v1/containers/{container}/dispatch', function () {
    it('returns 200 with dispatch summary on success', function () {
        $component = Product::factory()->serialised()->create();
        $container = apiTransportContainer($this->store);
        apiPackAndSeal($container, $this->store, $component);

        $opportunity = apiDispatchOrder($this->store, [
            $component->id => '1',
            $container->product_id => '1',
        ]);

        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/v1/containers/{$container->uuid}/dispatch", [
                'opportunity_id' => $opportunity->id,
            ])
            ->assertOk()
            ->assertJsonPath('dispatch.opportunity_id', $opportunity->id)
            ->assertJsonPath('dispatch.container.status', ContainerStatus::Dissolved->value);
    });

    it('returns 409 when amendments are required', function () {
        $missing = Product::factory()->serialised()->create(['is_warehouse_addable' => false]);
        $container = apiTransportContainer($this->store);
        apiPackAndSeal($container, $this->store, $missing);

        $operator = User::factory()->create();
        $operator->givePermissionTo([
            'containers.view',
            'opportunities.edit',
            'opportunities.dispatch_add_items',
            'opportunities.create',
            'stock.access',
        ]);
        MemberStore::create([
            'member_id' => $operator->member_id,
            'store_id' => $this->store->id,
            'created_at' => now(),
        ]);
        $token = $operator->createToken('test', ['containers:read', 'opportunities:write'])->plainTextToken;

        $opportunity = apiDispatchOrder($this->store, [
            $container->product_id => '1',
        ]);

        $this->actingAs($operator);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/containers/{$container->uuid}/dispatch", [
                'opportunity_id' => $opportunity->id,
            ])
            ->assertConflict()
            ->assertJsonStructure(['message', 'amendments', 'validation']);
    });

    it('returns 422 for guard failures', function () {
        $container = Container::factory()->transport()->create([
            'store_id' => $this->store->id,
            'status' => ContainerStatus::Open->value,
        ]);

        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/v1/containers/{$container->uuid}/dispatch", [
                'opportunity_id' => 1,
            ])
            ->assertUnprocessable();
    });
});
