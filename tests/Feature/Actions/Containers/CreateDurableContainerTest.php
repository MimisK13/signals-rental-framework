<?php

use App\Actions\Containers\CreateContainer;
use App\Actions\Containers\CreateDurableContainer;
use App\Data\Containers\CreateDurableContainerData;
use App\Enums\TransactionType;
use App\Models\Asset;
use App\Models\Container;
use App\Models\MemberStore;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockTransaction;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

/**
 * @param  array<string, mixed>  $overrides
 */
function durableContainerData(Product $product, Store $store, array $overrides = []): CreateDurableContainerData
{
    return CreateDurableContainerData::from(array_merge([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'asset_number' => 'FC-001',
        'serial_number' => 'SN-FC-001',
        'barcode' => 'FC-XLR-001',
        'name' => 'XLR CASE 01',
    ], $overrides));
}

it('creates one serialized inventory identity and one durable Container atomically', function (): void {
    $product = Product::factory()->containerable()->create(['name' => 'Standard XLR Flightcase']);
    $store = Store::factory()->create(['name' => 'Thessaloniki']);

    $result = app(CreateDurableContainer::class)(durableContainerData($product, $store));

    $stockLevel = StockLevel::query()->where('asset_number', 'FC-001')->firstOrFail();

    expect($result->product_id)->toBe($product->id)
        ->and($result->store_id)->toBe($store->id)
        ->and($result->name)->toBe('XLR CASE 01')
        ->and($result->barcode)->toBe('FC-XLR-001')
        ->and($result->is_temporary)->toBeFalse();

    expect(Asset::query()->where('product_id', $product->id)->count())->toBe(1)
        ->and(StockLevel::query()->where('asset_number', 'FC-001')->count())->toBe(1)
        ->and($stockLevel->quantity_held)->toBe('1.00')
        ->and($stockLevel->asset_id)->not->toBeNull();

    $this->assertDatabaseHas('stock_transactions', [
        'stock_level_id' => $stockLevel->id,
        'store_id' => $store->id,
        'transaction_type' => TransactionType::Opening->value,
        'quantity' => '1.00',
        'quantity_before' => '0.00',
        'quantity_after' => '1.00',
    ]);

    $this->assertDatabaseHas('containers', [
        'id' => $result->id,
        'serialised_item_id' => $stockLevel->id,
        'product_id' => $product->id,
        'store_id' => $store->id,
        'is_temporary' => false,
    ]);
});

it('rejects durable onboarding for a bulk Product without creating inventory', function (): void {
    $product = Product::factory()->bulk()->create();
    $store = Store::factory()->create();

    expect(fn () => app(CreateDurableContainer::class)(durableContainerData($product, $store)))
        ->toThrow(ValidationException::class);

    expect(StockLevel::query()->where('asset_number', 'FC-001')->exists())->toBeFalse()
        ->and(Asset::query()->where('product_id', $product->id)->exists())->toBeFalse()
        ->and(Container::query()->where('name', 'XLR CASE 01')->exists())->toBeFalse();
});

it('rejects durable onboarding for a non-containerable Product without creating inventory', function (): void {
    $product = Product::factory()->serialised()->create(['is_containerable' => false]);
    $store = Store::factory()->create();

    expect(fn () => app(CreateDurableContainer::class)(durableContainerData($product, $store)))
        ->toThrow(ValidationException::class);

    expect(StockLevel::query()->where('asset_number', 'FC-001')->exists())->toBeFalse()
        ->and(Asset::query()->where('product_id', $product->id)->exists())->toBeFalse();
});

it('rejects onboarding when the user cannot access the selected Store', function (): void {
    $store = Store::factory()->create();
    $otherStore = Store::factory()->create();
    $user = User::factory()->create();
    $user->givePermissionTo(['containers.create', 'stock.adjust']);
    MemberStore::create([
        'member_id' => $user->member_id,
        'store_id' => $otherStore->id,
        'created_at' => now(),
    ]);
    $this->actingAs($user);
    $product = Product::factory()->containerable()->create();

    expect(fn () => app(CreateDurableContainer::class)(durableContainerData($product, $store)))
        ->toThrow(AuthorizationException::class);

    expect(StockLevel::query()->where('asset_number', 'FC-001')->exists())->toBeFalse()
        ->and(Container::query()->where('name', 'XLR CASE 01')->exists())->toBeFalse();
});

it('allows Container creation permission to authorize the composed inventory onboarding', function (): void {
    $store = Store::factory()->create();
    $user = User::factory()->create();
    $user->givePermissionTo(['containers.create', 'stock.access']);
    MemberStore::create([
        'member_id' => $user->member_id,
        'store_id' => $store->id,
        'created_at' => now(),
    ]);
    $this->actingAs($user);
    $product = Product::factory()->containerable()->create();

    $result = app(CreateDurableContainer::class)(durableContainerData($product, $store));
    $stockLevel = StockLevel::query()->where('asset_number', 'FC-001')->firstOrFail();

    expect($result->store_id)->toBe($store->id)
        ->and(Container::query()->where('serialised_item_id', $stockLevel->id)->exists())->toBeTrue();
});

it('rolls back the serialized identity when Container creation fails', function (): void {
    $product = Product::factory()->containerable()->create();
    $store = Store::factory()->create();

    $this->mock(CreateContainer::class, function ($mock): void {
        $mock->shouldReceive('__invoke')
            ->once()
            ->andThrow(ValidationException::withMessages([
                'serialised_item_id' => 'Container creation failed.',
            ]));
    });

    expect(fn () => app(CreateDurableContainer::class)(durableContainerData($product, $store)))
        ->toThrow(ValidationException::class);

    expect(StockLevel::query()->where('asset_number', 'FC-001')->exists())->toBeFalse()
        ->and(Asset::query()->where('product_id', $product->id)->exists())->toBeFalse()
        ->and(StockTransaction::query()->where('description', 'Opening balance')->exists())->toBeFalse()
        ->and(Container::query()->where('name', 'XLR CASE 01')->exists())->toBeFalse();
});

it('rejects duplicate serialized identifiers without partial onboarding', function (): void {
    $product = Product::factory()->containerable()->create();
    $store = Store::factory()->create();
    StockLevel::factory()->serialised()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'asset_number' => 'FC-001',
    ]);

    expect(fn () => app(CreateDurableContainer::class)(durableContainerData($product, $store)))
        ->toThrow(ValidationException::class);

    expect(StockLevel::query()->where('asset_number', 'FC-001')->count())->toBe(1)
        ->and(Container::query()->where('name', 'XLR CASE 01')->exists())->toBeFalse();
});

it('rejects a durable Container barcode that collides with another serialized identifier', function (): void {
    $product = Product::factory()->containerable()->create();
    $otherProduct = Product::factory()->serialised()->create();
    $store = Store::factory()->create();
    StockLevel::factory()->serialised()->create([
        'product_id' => $otherProduct->id,
        'store_id' => $store->id,
        'barcode' => 'FC-XLR-001',
    ]);

    expect(fn () => app(CreateDurableContainer::class)(durableContainerData($product, $store)))
        ->toThrow(ValidationException::class);

    expect(StockLevel::query()->where('asset_number', 'FC-001')->exists())->toBeFalse()
        ->and(Container::query()->where('barcode', 'FC-XLR-001')->exists())->toBeFalse();
});
