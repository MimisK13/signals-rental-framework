<?php

use App\Models\Invoice;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Livewire\Volt\Volt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

it('renders the invoices index page', function () {
    $this->get(route('invoices.index'))
        ->assertOk()
        ->assertSee('Invoices');
});

it('lists invoices on the index page', function () {
    $invoice = Invoice::factory()->create(['number' => 'INV-LIST-1']);

    Volt::test('invoices.index')
        ->assertSee('INV-LIST-1');
});

it('filters invoices by status chip', function () {
    Invoice::factory()->create(['status' => 'draft', 'number' => 'INV-DRAFT']);
    Invoice::factory()->issued()->create(['number' => 'INV-ISSUED']);

    Volt::test('invoices.index')
        ->call('setStatusFilter', 'draft')
        ->assertSee('INV-DRAFT')
        ->assertDontSee('INV-ISSUED');
});
