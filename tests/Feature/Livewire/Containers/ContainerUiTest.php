<?php

use App\Actions\Containers\PackContainerItem;
use App\Data\Containers\PackContainerItemData;
use App\Enums\ContainerAvailabilityMode;
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

it('filters containers by store and mode on the index page', function () {
    $kitProduct = Product::factory()->containerable(ContainerAvailabilityMode::Kit)->create();
    $transportProduct = Product::factory()->containerable(ContainerAvailabilityMode::Transport)->create();

    $kit = Container::factory()->kit()->create([
        'store_id' => $this->store->id,
        'product_id' => $kitProduct->id,
    ]);

    $otherStore = Store::factory()->create();
    $transport = Container::factory()->transport()->create([
        'store_id' => $otherStore->id,
        'product_id' => $transportProduct->id,
        'name' => 'Transport Case Elsewhere',
    ]);

    Volt::test('containers.index')
        ->set('storeFilter', (string) $this->store->id)
        ->set('modeFilter', 'kit')
        ->assertSee($kit->name)
        ->assertDontSee($transport->name);
});

it('navigates to a container from a barcode scan', function () {
    $container = Container::factory()->kit()->create([
        'store_id' => $this->store->id,
        'barcode' => 'CASE-SCAN-001',
    ]);

    Volt::test('containers.index')
        ->set('scanBarcode', 'CASE-SCAN-001')
        ->call('submitScan')
        ->assertRedirect(route('containers.show', $container));
});

it('opens the pack page after scanning a containerable housing without an active container', function () {
    $product = Product::factory()->containerable()->create();
    $housing = StockLevel::factory()->serialised()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
        'asset_number' => 'HOUSING-001',
    ]);

    $component = Volt::test('containers.index')
        ->set('scanBarcode', 'HOUSING-001')
        ->call('submitScan');

    $created = Container::query()->where('serialised_item_id', $housing->id)->active()->firstOrFail();

    $component->assertRedirect(route('containers.pack', $created));
});

it('packs via the dedicated pack page with inline conflict resolution', function () {
    $source = Container::factory()->kit()->create(['store_id' => $this->store->id]);
    $target = Container::factory()->kit()->create(['store_id' => $this->store->id]);
    $asset = StockLevel::factory()->serialised()->create([
        'product_id' => Product::factory()->serialised()->create()->id,
        'store_id' => $this->store->id,
        'asset_number' => 'COMP-001',
    ]);

    (new PackContainerItem)($source, PackContainerItemData::from([
        'serialised_item_id' => $asset->id,
    ]));

    Volt::test('containers.pack', ['container' => $target])
        ->set('scanInput', 'COMP-001')
        ->call('scanItem')
        ->assertSet('packConflict.conflict', 'already_in_container')
        ->call('resolvePackConflict', 'transfer')
        ->assertHasNoErrors();

    expect(ContainerItem::query()
        ->where('container_id', $target->id)
        ->where('serialised_item_id', $asset->id)
        ->whereNull('unpacked_at')
        ->exists())->toBeTrue();
});

it('shows lifecycle timeline and dissolve action on the show page', function () {
    $container = Container::factory()->kit()->create(['store_id' => $this->store->id]);

    Volt::test('containers.show', ['container' => $container])
        ->assertSee('Lifecycle')
        ->assertSee('Contents')
        ->assertSee('Dissolve');
});

it('forbids the pack page without pack permission', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['containers.view', 'stock.access']);
    $this->actingAs($user);

    $container = Container::factory()->kit()->create(['store_id' => $this->store->id]);

    $this->get(route('containers.pack', $container))->assertForbidden();
});
