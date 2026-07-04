<?php

use App\Contracts\Availability\AvailabilityResolutionProvider;
use App\Enums\AvailabilityResolution;
use App\Enums\ContainerScanMode;
use App\Enums\ContainerStatus;
use App\Enums\ContainerUnsealReason;
use App\Jobs\DeliverWebhook;
use App\Models\Container;
use App\Models\ContainerItem;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use App\Models\Webhook;
use App\Services\Containers\ContainerManifestService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

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

/**
 * @return TestResponse<JsonResponse>
 */
function apiPackAsset(TestCase $testCase, Container $container, Store $store, Product $product, string $token): TestResponse
{
    $asset = StockLevel::factory()->serialised()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
    ]);

    return $testCase->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/containers/{$container->uuid}/pack", [
            'serialised_item_id' => $asset->id,
        ])
        ->assertCreated();
}

function apiNestedKitContainer(Store $store): Container
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
    ]);
}

describe('POST /api/v1/containers/{container}/seal', function () {
    it('seals an open container', function () {
        Queue::fake([DeliverWebhook::class]);

        $container = Container::factory()->kit()->create([
            'store_id' => $this->store->id,
            'scan_mode' => ContainerScanMode::Free->value,
        ]);
        apiPackAsset($this, $container, $this->store, Product::factory()->serialised()->create(), $this->token);

        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/v1/containers/{$container->uuid}/seal")
            ->assertOk()
            ->assertJsonPath('container.status', 'sealed');

        Queue::assertPushed(DeliverWebhook::class, fn (DeliverWebhook $job): bool => $job->event === 'container.sealed');
    });

    it('returns 422 for strict template mismatch', function () {
        $component = Product::factory()->serialised()->create();
        $housingProduct = Product::factory()->containerable()->create([
            'container_template' => [
                'slots' => [
                    ['product_id' => $component->id, 'quantity' => 2, 'binding' => 'fixed'],
                ],
            ],
        ]);
        $housing = StockLevel::factory()->serialised()->create([
            'product_id' => $housingProduct->id,
            'store_id' => $this->store->id,
        ]);
        $container = Container::factory()->create([
            'product_id' => $housingProduct->id,
            'serialised_item_id' => $housing->id,
            'store_id' => $this->store->id,
            'scan_mode' => ContainerScanMode::Strict->value,
        ]);
        apiPackAsset($this, $container, $this->store, $component, $this->token);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/v1/containers/{$container->uuid}/seal")
            ->assertUnprocessable();

        expect($response->json('errors'))->toHaveKey("products.{$component->id}");
    });

    it('requires containers:write ability', function () {
        $operator = User::factory()->create();
        $operator->givePermissionTo(['containers.view', 'containers.seal', 'stock.access']);
        $readToken = $operator->createToken('read', ['containers:read'])->plainTextToken;

        $container = Container::factory()->kit()->create(['store_id' => $this->store->id]);
        $asset = StockLevel::factory()->serialised()->create([
            'product_id' => Product::factory()->serialised()->create()->id,
            'store_id' => $this->store->id,
        ]);
        ContainerItem::factory()->create([
            'container_id' => $container->id,
            'serialised_item_id' => $asset->id,
            'product_id' => $asset->product_id,
        ]);

        $this->withHeader('Authorization', "Bearer {$readToken}")
            ->postJson("/api/v1/containers/{$container->uuid}/seal")
            ->assertForbidden();
    });
});

describe('POST /api/v1/containers/{container}/unseal', function () {
    it('unseals with a valid reason', function () {
        Queue::fake([DeliverWebhook::class]);

        $container = Container::factory()->kit()->sealed()->create(['store_id' => $this->store->id]);

        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/v1/containers/{$container->uuid}/unseal", [
                'reason' => ContainerUnsealReason::Manual->value,
            ])
            ->assertOk()
            ->assertJsonPath('container.status', 'open');

        Queue::assertPushed(DeliverWebhook::class, fn (DeliverWebhook $job): bool => $job->event === 'container.unsealed');
    });

    it('rejects missing reason', function () {
        $container = Container::factory()->kit()->sealed()->create(['store_id' => $this->store->id]);

        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/v1/containers/{$container->uuid}/unseal", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reason']);
    });

    it('rejects invalid reason', function () {
        $container = Container::factory()->kit()->sealed()->create(['store_id' => $this->store->id]);

        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/v1/containers/{$container->uuid}/unseal", [
                'reason' => 'invalid',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reason']);
    });
});

describe('GET /api/v1/containers/{container}/compliance', function () {
    it('returns flat template compliance', function () {
        $component = Product::factory()->serialised()->create();
        $housingProduct = Product::factory()->containerable()->create([
            'container_template' => [
                'slots' => [
                    ['product_id' => $component->id, 'quantity' => 2, 'binding' => 'fixed'],
                ],
            ],
        ]);
        $housing = StockLevel::factory()->serialised()->create([
            'product_id' => $housingProduct->id,
            'store_id' => $this->store->id,
        ]);
        $container = Container::factory()->create([
            'product_id' => $housingProduct->id,
            'serialised_item_id' => $housing->id,
            'store_id' => $this->store->id,
        ]);
        apiPackAsset($this, $container, $this->store, $component, $this->token);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/v1/containers/{$container->uuid}/compliance")
            ->assertOk();

        expect($response->json('compliance.compliant'))->toBeFalse()
            ->and($response->json('compliance.slots.0.status'))->toBe('under')
            ->and($response->json('compliance.is_sealed'))->toBeFalse();
    });

    it('includes nested contents in the manifest', function () {
        $component = Product::factory()->serialised()->create();
        $housingProduct = Product::factory()->containerable()->create([
            'container_max_nesting_depth' => 3,
            'container_template' => [
                'slots' => [
                    ['product_id' => $component->id, 'quantity' => 1, 'binding' => 'fixed'],
                ],
            ],
        ]);
        $rootHousing = StockLevel::factory()->serialised()->create([
            'product_id' => $housingProduct->id,
            'store_id' => $this->store->id,
        ]);
        $root = Container::factory()->create([
            'product_id' => $housingProduct->id,
            'serialised_item_id' => $rootHousing->id,
            'store_id' => $this->store->id,
        ]);

        $child = apiNestedKitContainer($this->store);
        apiPackAsset($this, $child, $this->store, $component, $this->token);
        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/v1/containers/{$root->uuid}/pack", [
                'serialised_item_id' => $child->serialised_item_id,
            ])
            ->assertCreated();

        $manifest = app(ContainerManifestService::class)->manifestQuantities($root);

        expect($manifest[$component->id] ?? 0)->toBe(1);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/v1/containers/{$root->uuid}/compliance")
            ->assertOk();

        expect($response->json('compliance.manifest.'.(string) $component->id))->toBe(1)
            ->and($response->json('compliance.slots.0.status'))->toBe('ok');
    });

    it('reports unexpected products', function () {
        $expected = Product::factory()->serialised()->create();
        $unexpected = Product::factory()->serialised()->create();
        $housingProduct = Product::factory()->containerable()->create([
            'container_template' => [
                'slots' => [
                    ['product_id' => $expected->id, 'quantity' => 1, 'binding' => 'fixed'],
                ],
            ],
        ]);
        $container = Container::factory()->create([
            'product_id' => $housingProduct->id,
            'serialised_item_id' => StockLevel::factory()->serialised()->create([
                'product_id' => $housingProduct->id,
                'store_id' => $this->store->id,
            ])->id,
            'store_id' => $this->store->id,
        ]);
        apiPackAsset($this, $container, $this->store, $unexpected, $this->token);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/v1/containers/{$container->uuid}/compliance")
            ->assertOk();

        $statuses = collect($response->json('compliance.slots'))->pluck('status')->all();

        expect($response->json('compliance.compliant'))->toBeFalse()
            ->and($statuses)->toContain('under')
            ->and($statuses)->toContain('unexpected');
    });

    it('requires containers:read ability', function () {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo(['containers.view', 'stock.access']);
        $writeToken = $viewer->createToken('write', ['containers:write'])->plainTextToken;
        $container = Container::factory()->kit()->create(['store_id' => $this->store->id]);

        $this->withHeader('Authorization', "Bearer {$writeToken}")
            ->getJson("/api/v1/containers/{$container->uuid}/compliance")
            ->assertForbidden();
    });
});

describe('compliance service depth limit', function () {
    it('respects max nesting depth when rolling up nested contents', function () {
        $component = Product::factory()->serialised()->create();
        $deepProduct = Product::factory()->containerable()->create([
            'container_max_nesting_depth' => 2,
        ]);

        $level3 = apiNestedKitContainer($this->store);
        apiPackAsset($this, $level3, $this->store, $component, $this->token);

        $level2 = apiNestedKitContainer($this->store);
        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/v1/containers/{$level2->uuid}/pack", [
                'serialised_item_id' => $level3->serialised_item_id,
            ])
            ->assertCreated();

        $root = Container::factory()->create([
            'product_id' => $deepProduct->id,
            'serialised_item_id' => StockLevel::factory()->serialised()->create([
                'product_id' => $deepProduct->id,
                'store_id' => $this->store->id,
            ])->id,
            'store_id' => $this->store->id,
        ]);
        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/v1/containers/{$root->uuid}/pack", [
                'serialised_item_id' => $level2->serialised_item_id,
            ])
            ->assertCreated();

        $manifest = app(ContainerManifestService::class)->manifestQuantities($root);

        expect($manifest)->not->toHaveKey($component->id);
    });
});
