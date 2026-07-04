<?php

use App\Models\Container;
use App\Models\ContainerItem;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Livewire\Volt\Volt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
    $this->store = Store::factory()->create();
});

it('renders the containers index page', function () {
    Container::factory()->kit()->create(['store_id' => $this->store->id]);

    $this->get(route('containers.index'))
        ->assertOk();

    Volt::test('containers.index')
        ->assertSee('Containers');
});

it('renders the container show page with pack affordance', function () {
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

    Volt::test('containers.show', ['container' => $container])
        ->assertSee($container->name)
        ->assertSee('Pack item')
        ->assertSee((string) $asset->id);
});

it('packs an item from the show page', function () {
    $container = Container::factory()->kit()->create(['store_id' => $this->store->id]);
    $asset = StockLevel::factory()->serialised()->create([
        'product_id' => Product::factory()->serialised()->create()->id,
        'store_id' => $this->store->id,
    ]);

    Volt::test('containers.show', ['container' => $container])
        ->set('packSerialisedItemId', (string) $asset->id)
        ->call('pack')
        ->assertHasNoErrors();

    expect(ContainerItem::query()
        ->where('container_id', $container->id)
        ->where('serialised_item_id', $asset->id)
        ->whereNull('unpacked_at')
        ->exists())->toBeTrue();
});

it('forbids the index page without containers.view', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('stock.access');
    $this->actingAs($user);

    $this->get(route('containers.index'))->assertForbidden();
});
