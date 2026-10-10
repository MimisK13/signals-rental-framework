<?php

use App\Actions\Containers\CreateContainer;
use App\Actions\Containers\CreateTemporaryContainer;
use App\Data\Containers\CreateContainerData;
use App\Data\Containers\CreateTemporaryContainerData;
use App\Models\Container;
use App\Models\MemberStore;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use App\Services\Containers\ContainerBarcodeResolver;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
});

it('rejects creating a Container for a housing StockLevel in an inaccessible Store', function (): void {
    $store = Store::factory()->create();
    $otherStore = Store::factory()->create();
    $product = Product::factory()->containerable()->create();
    $housing = StockLevel::factory()->serialised()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
    ]);
    $user = User::factory()->create();
    $user->givePermissionTo('containers.create');
    MemberStore::create([
        'member_id' => $user->member_id,
        'store_id' => $otherStore->id,
        'created_at' => now(),
    ]);
    $this->actingAs($user);

    expect(fn () => (new CreateContainer)(CreateContainerData::from([
        'serialised_item_id' => $housing->id,
    ])))->toThrow(AuthorizationException::class);

    expect(Container::query()->where('serialised_item_id', $housing->id)->exists())->toBeFalse();
});

it('rejects creating a temporary Container for an inaccessible Store', function (): void {
    $store = Store::factory()->create();
    $otherStore = Store::factory()->create();
    $user = User::factory()->create();
    $user->givePermissionTo('containers.create_temporary');
    MemberStore::create([
        'member_id' => $user->member_id,
        'store_id' => $otherStore->id,
        'created_at' => now(),
    ]);
    $this->actingAs($user);

    expect(fn () => (new CreateTemporaryContainer)(CreateTemporaryContainerData::from([
        'barcode' => 'TEMP-UNAUTHORIZED',
        'store_id' => $store->id,
    ])))->toThrow(AuthorizationException::class);

    expect(Container::query()->where('barcode', 'TEMP-UNAUTHORIZED')->exists())->toBeFalse();
});

it('rejects a temporary barcode that collides with a serialized StockLevel barcode', function (): void {
    $store = Store::factory()->create();
    $product = Product::factory()->serialised()->create();
    StockLevel::factory()->serialised()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'barcode' => 'SERIALIZED-BARCODE',
    ]);
    $this->actingAs(User::factory()->owner()->create());

    expect(fn () => (new CreateTemporaryContainer)(CreateTemporaryContainerData::from([
        'barcode' => 'SERIALIZED-BARCODE',
        'store_id' => $store->id,
    ])))->toThrow(ValidationException::class);
});

it('resolves a Product-backed Container by its inherited housing barcode', function (): void {
    $store = Store::factory()->create();
    $product = Product::factory()->containerable()->create();
    $housing = StockLevel::factory()->serialised()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'barcode' => 'CASE-BARCODE',
    ]);
    $this->actingAs(User::factory()->owner()->create());

    $container = (new CreateContainer)(CreateContainerData::from([
        'serialised_item_id' => $housing->id,
    ]));

    $result = app(ContainerBarcodeResolver::class)->resolve('CASE-BARCODE');

    expect($result)->toMatchArray(['type' => 'container'])
        ->and($result['container']->id)->toBe($container->id);
});

it('rejects an unrelated identifier collision instead of guessing', function (): void {
    $store = Store::factory()->create();
    $product = Product::factory()->serialised()->create();
    $otherProduct = Product::factory()->serialised()->create();
    StockLevel::factory()->serialised()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'barcode' => 'AMBIGUOUS-001',
    ]);
    StockLevel::factory()->serialised()->create([
        'product_id' => $otherProduct->id,
        'store_id' => $store->id,
        'barcode' => 'AMBIGUOUS-001',
    ]);
    $this->actingAs(User::factory()->owner()->create());

    expect(fn () => app(ContainerBarcodeResolver::class)->resolve('AMBIGUOUS-001'))
        ->toThrow(ValidationException::class);
});
