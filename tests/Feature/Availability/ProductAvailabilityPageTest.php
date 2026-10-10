<?php

use App\Enums\DemandPhase;
use App\Models\AvailabilitySnapshot;
use App\Models\Demand;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);

    $this->store = Store::factory()->create(['timezone' => 'UTC', 'is_default' => true]);
    $this->user = User::factory()->create();
    $this->user->givePermissionTo(['stock.access', 'availability.view']);

    Carbon::setTestNow(Carbon::parse('2026-11-10 08:00:00', 'UTC'));
});

afterEach(function () {
    Carbon::setTestNow();
});

function seedProductAvailability(Product $product, Store $store): void
{
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'quantity_held' => 4,
    ]);

    foreach ([
        ['2026-11-10 08:00:00', 4, 2],
        ['2026-11-11 08:00:00', 4, 5],
        ['2026-11-12 08:00:00', 4, 3],
    ] as [$slot, $stock, $demand]) {
        AvailabilitySnapshot::factory()->slot(Carbon::parse($slot, 'UTC'), $stock, $demand)->create([
            'product_id' => $product->id,
            'store_id' => $store->id,
        ]);
    }
}

it('renders a store-scoped product availability summary from canonical snapshots', function () {
    $product = Product::factory()->create(['name' => 'Midas M32']);
    seedProductAvailability($product, $this->store);

    $this->actingAs($this->user);

    $component = Volt::test('products.availability', ['product' => $product])
        ->assertOk()
        ->assertSet('storeId', $this->store->id)
        ->assertSee('Midas M32')
        ->assertSee('Availability')
        ->assertSee('Physical Stock')
        ->assertSee('Peak Demand')
        ->assertSee('5')
        ->assertSee('Lowest Availability')
        ->assertSee('-1')
        ->assertSee('Peak Shortage')
        ->assertSee('1')
        ->assertSee('Shortage')
        ->assertSee('Yes')
        ->assertSee('Availability Over Time');

    expect($component->get('summary')->peak_demand)->toBe(5);
});

it('recalculates the summary when the selected store changes', function () {
    $product = Product::factory()->create(['name' => 'Multi-store Product']);
    $secondStore = Store::factory()->create(['timezone' => 'UTC', 'is_default' => true]);

    seedProductAvailability($product, $this->store);
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $secondStore->id,
        'quantity_held' => 7,
    ]);
    AvailabilitySnapshot::factory()->slot(Carbon::parse('2026-11-10 08:00:00', 'UTC'), 7, 1)->create([
        'product_id' => $product->id,
        'store_id' => $secondStore->id,
    ]);

    $this->actingAs($this->user);

    $component = Volt::test('products.availability', ['product' => $product])
        ->set('storeId', $secondStore->id);

    expect($component->get('summary')->physical_stock)->toBe(7)
        ->and($component->get('summary')->peak_demand)->toBe(1);
});

it('shows opportunity demand in a human-readable breakdown without adding a second demand calculation', function () {
    $product = Product::factory()->create(['name' => 'Midas M32']);
    seedProductAvailability($product, $this->store);

    $opportunity = Opportunity::factory()->create(['subject' => 'Festival A']);

    Demand::factory()->phase(DemandPhase::Committed)->window(
        Carbon::parse('2026-11-10 08:00:00', 'UTC'),
        Carbon::parse('2026-11-12 12:00:00', 'UTC'),
    )->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
        'quantity' => 2,
        'source_type' => 'opportunity_item',
        'source_id' => 12345,
        'metadata' => ['opportunity_id' => $opportunity->id],
    ]);

    $this->actingAs($this->user);

    Volt::test('products.availability', ['product' => $product])
        ->assertSee('Demand Breakdown')
        ->assertSee('Festival A')
        ->assertSee('2')
        ->assertSee('Committed');
});

it('forbids product availability without availability.view', function () {
    $this->actingAs(User::factory()->create());

    Volt::test('products.availability', ['product' => Product::factory()->create()])
        ->assertForbidden();
});

it('does not claim period metrics when no canonical snapshots exist', function () {
    $product = Product::factory()->create(['name' => 'No Snapshot Product']);
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
        'quantity_held' => 4,
    ]);

    $this->actingAs($this->user);

    Volt::test('products.availability', ['product' => $product])
        ->assertSee('No availability snapshot exists for this period yet.')
        ->assertSee('No availability slots are available for this period.');
});
