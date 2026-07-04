<?php

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
        ->assertSee('Virtual Stock');
});

it('renders the virtual stock show page', function () {
    $intake = createTestVirtualStockIntake();

    Volt::test('virtual-stock.show', ['virtualStockIntake' => $intake])
        ->assertSee($intake->number);
});

it('renders the create form', function () {
    $this->get(route('virtual-stock.create'))->assertOk();

    Volt::test('virtual-stock.form')
        ->assertSee('New Virtual Stock Intake');
});
