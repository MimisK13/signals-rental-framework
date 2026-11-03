<?php

use App\Domain\Administration\Database\Seeders\PermissionSeeder;
use App\Domain\Administration\Database\Seeders\RoleSeeder;
use App\Domain\Administration\Models\User;
use App\Domain\Availability\Models\AvailabilityDailySummary;
use App\Domain\Products\Models\Product;
use App\Domain\Products\Models\ProductGroup;
use App\Domain\Stock\Models\StockLevel;
use App\Domain\Stores\Models\Store;
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

    Carbon::setTestNow(Carbon::parse('2026-06-20 09:00:00', 'UTC'));
});

afterEach(function () {
    Carbon::setTestNow();
});

it('renders the availability page for a user with availability.view', function () {
    $this->actingAs($this->user);

    Volt::test('availability.index')->call('initializeGrid')
        ->assertOk()
        ->assertSee('Equipment Availability')
        ->assertSee('Product Group')
        ->assertDontSee('Calendar')
        ->assertDontSee('Timeline');
});

it('forbids the page for a user without availability.view', function () {
    $this->actingAs(User::factory()->create());

    Volt::test('availability.index')->assertForbidden();
});

it('defaults to today, all stores, and a 30-day period', function () {
    $this->actingAs($this->user);

    Volt::test('availability.index')->call('initializeGrid')
        ->assertSet('storeIds', [])
        ->assertSet('from', '2026-06-20')
        ->assertSet('daysPeriod', 30);
});

it('re-renders without error when the store availability changes', function () {
    $group = ProductGroup::factory()->create(['name' => 'Speakers']);
    $product = Product::factory()->create(['name' => 'Live Product', 'product_group_id' => $group->id]);

    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
        'quantity_held' => 5,
    ]);

    $this->actingAs($this->user);

    $component = Volt::test('availability.index')->call('initializeGrid')->set('groupId', $group->id);
    $component->assertOk();

    AvailabilityDailySummary::factory()
        ->day(Carbon::parse('2026-06-20', 'UTC'), 4, 4)
        ->create(['product_id' => $product->id, 'store_id' => $this->store->id]);

    $component->call('onStoreAvailabilityChanged')
        ->assertOk()
        ->assertSee('Live Product');
});
