<?php

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
use Livewire\Volt\Volt;

beforeEach(function (): void {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
    $this->store = Store::factory()->create(['name' => 'Thessaloniki']);
});

it('renders the durable Container onboarding page with searchable products and Stores', function (): void {
    Product::factory()->containerable()->create(['name' => 'M32 Flightcase']);

    $this->get(route('containers.create'))->assertOk();

    Volt::test('containers.create')
        ->set('productSearch', 'M32')
        ->assertSee('M32 Flightcase')
        ->assertSee('Thessaloniki')
        ->assertSee('Asset Number')
        ->assertSee('Create Container');
});

it('creates a durable Container through the canonical onboarding Action', function (): void {
    $product = Product::factory()->containerable()->create(['name' => 'Standard XLR Flightcase']);

    Volt::test('containers.create')
        ->set('productId', $product->id)
        ->set('storeId', $this->store->id)
        ->set('assetNumber', 'FC-001')
        ->set('serialNumber', 'SN-FC-001')
        ->set('barcode', 'FC-XLR-001')
        ->set('name', 'XLR CASE 01')
        ->call('create')
        ->assertRedirect(route('containers.pack', Container::query()->latest('id')->firstOrFail()));

    $stockLevel = StockLevel::query()->where('asset_number', 'FC-001')->firstOrFail();
    $container = Container::query()->where('serialised_item_id', $stockLevel->id)->firstOrFail();

    expect(Asset::query()->where('product_id', $product->id)->count())->toBe(1)
        ->and($stockLevel->quantity_held)->toBe('1.00')
        ->and($container->product_id)->toBe($product->id)
        ->and($container->store_id)->toBe($this->store->id)
        ->and($container->name)->toBe('XLR CASE 01');

    expect(StockTransaction::query()
        ->where('stock_level_id', $stockLevel->id)
        ->where('transaction_type', TransactionType::Opening->value)
        ->count())->toBe(1);
});

it('validates required onboarding fields without creating inventory', function (): void {
    Volt::test('containers.create')
        ->set('storeId', null)
        ->call('create')
        ->assertHasErrors(['productId', 'storeId', 'assetNumber']);

    expect(Asset::query()->count())->toBe(0)
        ->and(StockLevel::query()->count())->toBe(0)
        ->and(Container::query()->count())->toBe(0);
});

it('surfaces an ineligible Product on the Product field', function (): void {
    $product = Product::factory()->bulk()->create(['name' => 'Bulk Cable']);

    Volt::test('containers.create')
        ->set('productId', $product->id)
        ->set('storeId', $this->store->id)
        ->set('assetNumber', 'FC-001')
        ->call('create')
        ->assertHasErrors('productId');

    expect(StockLevel::query()->where('asset_number', 'FC-001')->exists())->toBeFalse()
        ->and(Container::query()->where('name', 'Bulk Cable')->exists())->toBeFalse();
});

it('preserves entered identity when a duplicate Asset Number is rejected', function (): void {
    $product = Product::factory()->containerable()->create();
    StockLevel::factory()->serialised()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
        'asset_number' => 'FC-001',
    ]);

    Volt::test('containers.create')
        ->set('productId', $product->id)
        ->set('storeId', $this->store->id)
        ->set('assetNumber', 'FC-001')
        ->set('barcode', 'FC-XLR-001')
        ->set('name', 'XLR CASE 01')
        ->call('create')
        ->assertHasErrors('assetNumber')
        ->assertSet('assetNumber', 'FC-001')
        ->assertSet('barcode', 'FC-XLR-001')
        ->assertSet('name', 'XLR CASE 01');
});

it('surfaces a duplicate barcode on the Barcode field', function (): void {
    $product = Product::factory()->containerable()->create();
    $otherProduct = Product::factory()->serialised()->create();
    StockLevel::factory()->serialised()->create([
        'product_id' => $otherProduct->id,
        'store_id' => $this->store->id,
        'barcode' => 'FC-XLR-001',
    ]);

    Volt::test('containers.create')
        ->set('productId', $product->id)
        ->set('storeId', $this->store->id)
        ->set('assetNumber', 'FC-001')
        ->set('barcode', 'FC-XLR-001')
        ->call('create')
        ->assertHasErrors('barcode');
});

it('requires access to the selected Store even when the UI input is tampered with', function (): void {
    $user = User::factory()->create();
    $user->givePermissionTo(['containers.create', 'stock.access']);
    $allowedStore = Store::factory()->create();
    $blockedStore = Store::factory()->create();
    MemberStore::create([
        'member_id' => $user->member_id,
        'store_id' => $allowedStore->id,
        'created_at' => now(),
    ]);
    $this->actingAs($user);
    $product = Product::factory()->containerable()->create();

    Volt::test('containers.create')
        ->set('productId', $product->id)
        ->set('storeId', $blockedStore->id)
        ->set('assetNumber', 'FC-UNAUTHORIZED')
        ->call('create')
        ->assertForbidden();

    expect(StockLevel::query()->where('asset_number', 'FC-UNAUTHORIZED')->exists())->toBeFalse()
        ->and(Container::query()->where('serialised_item_id', 'FC-UNAUTHORIZED')->exists())->toBeFalse();
});

it('forbids the onboarding page without Container create permission', function (): void {
    $user = User::factory()->create();
    $user->givePermissionTo(['containers.view', 'stock.access']);
    $this->actingAs($user);

    $this->get(route('containers.create'))->assertForbidden();
});
