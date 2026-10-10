<?php

use App\Enums\OpportunityStatus;
use App\Models\Opportunity;
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
});

it('renders the warehouse work queues', function (): void {
    Volt::test('warehouse.index')
        ->assertSee('Needs preparation')
        ->assertSee('Ready / going out')
        ->assertSee('On hire')
        ->assertSee('Returns / checking');
});

it('renders an independent collapse control for each warehouse queue card', function (): void {
    $html = Volt::test('warehouse.index')->html();

    expect(substr_count($html, 'aria-label="Toggle card"'))->toBe(4);
});

it('renders each warehouse queue as a full-bleed table card', function (): void {
    $html = Volt::test('warehouse.index')->html();

    expect(substr_count($html, 'class="card table-card"'))->toBe(4)
        ->and(substr_count($html, 's-table-wrap'))->toBe(0);
});

it('shows order work in the matching queue and links to execution', function (): void {
    $store = Store::factory()->create(['name' => 'Main Warehouse']);
    $opportunity = Opportunity::factory()->order()->create([
        'number' => 'ORD-1001',
        'subject' => 'Conference rig',
        'store_id' => $store->id,
        'status' => OpportunityStatus::OrderActive->statusValue(),
    ]);

    Volt::test('warehouse.index')
        ->assertSee('ORD-1001')
        ->assertSee('Conference rig')
        ->assertSee('Main Warehouse')
        ->assertSee(route('opportunities.assets', $opportunity));
});

it('filters the work queues by store', function (): void {
    $visibleStore = Store::factory()->create(['name' => 'Visible Store']);
    $hiddenStore = Store::factory()->create(['name' => 'Hidden Store']);

    Opportunity::factory()->order()->create([
        'number' => 'ORD-VISIBLE',
        'store_id' => $visibleStore->id,
    ]);
    Opportunity::factory()->order()->create([
        'number' => 'ORD-HIDDEN',
        'store_id' => $hiddenStore->id,
    ]);

    Volt::test('warehouse.index')
        ->set('storeFilter', (string) $visibleStore->id)
        ->assertSee('ORD-VISIBLE')
        ->assertDontSee('ORD-HIDDEN');
});

it('forbids users without opportunity access', function (): void {
    $user = User::factory()->create();
    $user->givePermissionTo('opportunities.access');

    $this->actingAs($user);

    Volt::test('warehouse.index')->assertForbidden();
});

it('surfaces warehouse in desktop and mobile navigation', function (): void {
    $html = $this->get(route('dashboard'))
        ->assertOk()
        ->getContent();

    expect(substr_count($html, route('warehouse.index')))->toBe(2);
});
