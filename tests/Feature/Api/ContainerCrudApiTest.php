<?php

use App\Contracts\Availability\AvailabilityResolutionProvider;
use App\Enums\AvailabilityResolution;
use App\Jobs\DeliverWebhook;
use App\Models\Container;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use App\Models\Webhook;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();

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
    $this->token = $this->owner->createToken('test', ['containers:read', 'containers:write'])->plainTextToken;
});

function apiHousingAsset(Store $store): StockLevel
{
    $product = Product::factory()->containerable()->create();

    return StockLevel::factory()->serialised()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'barcode' => 'API-CASE-1',
    ]);
}

describe('POST /api/v1/containers', function () {
    it('creates a container from a containerable serialised item', function () {
        Queue::fake([DeliverWebhook::class]);

        $housing = apiHousingAsset($this->store);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/containers', [
                'serialised_item_id' => $housing->id,
            ])
            ->assertCreated()
            ->assertJsonPath('container.serialised_item_id', $housing->id);

        expect($response->json('container.uuid'))->not->toBeEmpty();

        Queue::assertPushed(DeliverWebhook::class, fn (DeliverWebhook $job): bool => $job->event === 'container.created');
    });

    it('rejects non-containerable products', function () {
        $product = Product::factory()->serialised()->create(['is_containerable' => false]);
        $housing = StockLevel::factory()->serialised()->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
        ]);

        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/containers', [
                'serialised_item_id' => $housing->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('serialised_item_id');
    });
});

describe('POST /api/v1/containers/temporary', function () {
    it('creates a temporary container with a required unique barcode', function () {
        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/containers/temporary', [
                'barcode' => 'TEMP-API-1',
                'store_id' => $this->store->id,
            ])
            ->assertCreated()
            ->assertJsonPath('container.is_temporary', true)
            ->assertJsonPath('container.barcode', 'TEMP-API-1');
    });

    it('rejects duplicate active barcodes', function () {
        Container::factory()->temporary()->create([
            'barcode' => 'TEMP-DUP-API',
            'store_id' => $this->store->id,
        ]);

        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/containers/temporary', [
                'barcode' => 'TEMP-DUP-API',
                'store_id' => $this->store->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('barcode');
    });
});

describe('PATCH /api/v1/containers/{container}', function () {
    it('updates open containers only', function () {
        $container = Container::factory()->kit()->create(['store_id' => $this->store->id]);

        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->patchJson("/api/v1/containers/{$container->uuid}", [
                'name' => 'Updated via API',
            ])
            ->assertOk()
            ->assertJsonPath('container.name', 'Updated via API');

        $sealed = Container::factory()->kit()->sealed()->create(['store_id' => $this->store->id]);

        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->patchJson("/api/v1/containers/{$sealed->uuid}", [
                'name' => 'Nope',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('container');
    });
});

describe('route binding', function () {
    it('resolves containers by uuid', function () {
        $container = Container::factory()->kit()->create(['store_id' => $this->store->id]);

        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/v1/containers/{$container->uuid}")
            ->assertOk()
            ->assertJsonPath('container.uuid', $container->uuid);
    });

    it('still resolves containers by integer id for backward compatibility', function () {
        $container = Container::factory()->kit()->create(['store_id' => $this->store->id]);

        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/v1/containers/{$container->id}")
            ->assertOk()
            ->assertJsonPath('container.id', $container->id);
    });
});

describe('authorization', function () {
    it('returns forbidden without containers.create for store', function () {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo(['containers.view', 'stock.access']);
        $token = $viewer->createToken('test', ['containers:write'])->plainTextToken;
        $housing = apiHousingAsset($this->store);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/containers', [
                'serialised_item_id' => $housing->id,
            ])
            ->assertForbidden();
    });

    it('returns forbidden without containers.create_temporary for temporary create', function () {
        $creator = User::factory()->create();
        $creator->givePermissionTo(['containers.view', 'stock.access', 'containers.create']);
        $token = $creator->createToken('test', ['containers:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/containers/temporary', [
                'barcode' => 'TEMP-FORBIDDEN',
                'store_id' => $this->store->id,
            ])
            ->assertForbidden();
    });
});

describe('pack/unpack webhooks', function () {
    it('dispatches packed and unpacked webhook events', function () {
        Queue::fake([DeliverWebhook::class]);

        $container = Container::factory()->kit()->create(['store_id' => $this->store->id]);
        $asset = StockLevel::factory()->serialised()->create([
            'product_id' => Product::factory()->serialised()->create()->id,
            'store_id' => $this->store->id,
        ]);

        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/v1/containers/{$container->uuid}/pack", [
                'serialised_item_id' => $asset->id,
            ])
            ->assertCreated();

        Queue::assertPushed(DeliverWebhook::class, fn (DeliverWebhook $job): bool => $job->event === 'container.item.packed');

        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/v1/containers/{$container->uuid}/unpack", [
                'serialised_item_id' => $asset->id,
            ])
            ->assertOk();

        Queue::assertPushed(DeliverWebhook::class, fn (DeliverWebhook $job): bool => $job->event === 'container.item.unpacked');
    });
});
