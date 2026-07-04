<?php

use App\Actions\VirtualStock\ConfirmVirtualStockIntake;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\ShortageResolution;
use App\Models\ShortageResolutionItem;
use App\Models\User;
use App\Models\VirtualStockIntake;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Livewire\Volt\Volt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

it('renders the virtual stock index page', function () {
    VirtualStockIntake::factory()->create();

    $this->get(route('virtual-stock.index'))
        ->assertOk();

    Volt::test('virtual-stock.index')
        ->assertSee('Virtual Stock')
        ->assertSee('Catalogue');
});

it('renders the virtual stock show page', function () {
    $intake = createTestVirtualStockIntake();

    Volt::test('virtual-stock.show', ['virtualStockIntake' => $intake])
        ->assertSee($intake->number);
});

it('shows cost apportionment panel after confirm', function () {
    $intake = createTestVirtualStockIntake(['quantity' => 2, 'unit_cost' => 1500]);
    $opportunity = Opportunity::factory()->create();
    $resolution = ShortageResolution::factory()->create([
        'virtual_stock_intake_id' => $intake->id,
        'metadata' => ['opportunity_id' => $opportunity->id],
    ]);
    ShortageResolutionItem::factory()->create([
        'shortage_resolution_id' => $resolution->id,
        'opportunity_item_id' => OpportunityItem::factory()->for($opportunity)->create()->id,
        'quantity_allocated' => 2,
    ]);

    app(ConfirmVirtualStockIntake::class)($intake->refresh());

    Volt::test('virtual-stock.show', ['virtualStockIntake' => $intake->refresh()])
        ->assertSee('Cost apportionment')
        ->assertSee('Primary job');
});

it('renders the create form', function () {
    $this->get(route('virtual-stock.create'))->assertOk();

    Volt::test('virtual-stock.form')
        ->assertSee('New Virtual Stock Intake');
});
