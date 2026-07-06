<?php

use App\Actions\Containers\DispatchContainer;
use App\Actions\Containers\PackContainerItem;
use App\Actions\Containers\ReturnKitContainer;
use App\Actions\Containers\SealContainer;
use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\ConvertToOrder;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Contracts\Availability\AvailabilityResolutionProvider;
use App\Data\Containers\DispatchContainerData;
use App\Data\Containers\PackContainerItemData;
use App\Data\Containers\ReturnKitContainerData;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Enums\AvailabilityResolution;
use App\Enums\ContainerAvailabilityMode;
use App\Enums\ContainerCheckinMode;
use App\Enums\ContainerStatus;
use App\Enums\LineItemTransactionType;
use App\Models\Container;
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

function apiKitContainer(Store $store): Container
{
    $product = Product::factory()->containerable(ContainerAvailabilityMode::Kit)->create([
        'container_checkin_mode' => ContainerCheckinMode::Parent->value,
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

function apiKitOrder(Store $store, int $kitProductId): Opportunity
{
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'API kit return',
        'store_id' => $store->id,
        'starts_at' => '2026-09-01T09:00:00Z',
        'ends_at' => '2026-09-05T17:00:00Z',
    ]));
    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();
    (new ConvertToQuotation)($opportunity);
    (new AddOpportunityItem)($opportunity->refresh(), AddOpportunityItemData::from([
        'name' => 'Kit',
        'itemable_id' => $kitProductId,
        'itemable_type' => Product::class,
        'quantity' => '1',
        'transaction_type' => LineItemTransactionType::Rental->value,
    ]));
    (new ConvertToOrder)($opportunity->refresh());

    return $opportunity->fresh();
}

describe('POST /api/v1/containers/{container}/dispatch kit path', function () {
    it('dispatches kit containers without dissolving', function () {
        $component = Product::factory()->serialised()->create();
        $container = apiKitContainer($this->store);

        (new PackContainerItem)($container, PackContainerItemData::from([
            'serialised_item_id' => StockLevel::factory()->serialised()->create([
                'product_id' => $component->id,
                'store_id' => $this->store->id,
            ])->id,
        ]));
        (new SealContainer)($container);

        $opportunity = apiKitOrder($this->store, (int) $container->product_id);

        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/v1/containers/{$container->uuid}/dispatch", [
                'opportunity_id' => $opportunity->id,
            ])
            ->assertOk()
            ->assertJsonPath('dispatch.container.status', ContainerStatus::Dispatched->value)
            ->assertJsonPath('dispatch.containers_dissolved', 0);
    });
});

describe('POST /api/v1/containers/{container}/return', function () {
    it('returns a dispatched kit', function () {
        $container = apiKitContainer($this->store);
        (new PackContainerItem)($container, PackContainerItemData::from([
            'serialised_item_id' => StockLevel::factory()->serialised()->create([
                'product_id' => Product::factory()->serialised()->create()->id,
                'store_id' => $this->store->id,
            ])->id,
        ]));
        (new SealContainer)($container);

        $opportunity = apiKitOrder($this->store, (int) $container->product_id);
        (new DispatchContainer)($container, DispatchContainerData::from(['opportunity_id' => $opportunity->id]));

        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/v1/containers/{$container->uuid}/return", [])
            ->assertOk()
            ->assertJsonPath('return.container.status', ContainerStatus::Returned->value);
    });
});

describe('POST /api/v1/containers/{container}/verify-checkin', function () {
    it('clears verification flag after parent_then_verify return', function () {
        $product = Product::factory()->containerable(ContainerAvailabilityMode::Kit)->create([
            'container_checkin_mode' => ContainerCheckinMode::ParentThenVerify->value,
        ]);
        $housing = StockLevel::factory()->serialised()->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
        ]);
        $container = Container::factory()->kit()->create([
            'product_id' => $product->id,
            'serialised_item_id' => $housing->id,
            'store_id' => $this->store->id,
        ]);

        (new PackContainerItem)($container, PackContainerItemData::from([
            'serialised_item_id' => StockLevel::factory()->serialised()->create([
                'product_id' => Product::factory()->serialised()->create()->id,
                'store_id' => $this->store->id,
            ])->id,
        ]));
        (new SealContainer)($container);

        $opportunity = apiKitOrder($this->store, $product->id);
        (new DispatchContainer)($container, DispatchContainerData::from(['opportunity_id' => $opportunity->id]));
        (new ReturnKitContainer)($container->fresh(), ReturnKitContainerData::from([]));

        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/v1/containers/{$container->uuid}/verify-checkin")
            ->assertOk()
            ->assertJsonPath('container.checkin_verification_required_at', null);
    });
});
