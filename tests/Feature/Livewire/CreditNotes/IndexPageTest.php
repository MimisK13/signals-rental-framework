<?php

use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Livewire\Volt\Volt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
});

it('renders the credit notes index page for a permitted user', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['invoices.access', 'invoices.view']);

    $this->actingAs($user)
        ->get(route('credit-notes.index'))
        ->assertOk()
        ->assertSee('Credit Notes');
});

it('lists credit notes on the index page', function () {
    $creditNote = CreditNote::factory()->create(['number' => 'CN-LIST-1']);

    Volt::actingAs(User::factory()->owner()->create())
        ->test('credit-notes.index')
        ->assertSee('CN-LIST-1')
        ->assertSee($creditNote->invoice->number);
});

it('forbids the credit notes index page without invoices.view', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('invoices.access');

    CreditNote::factory()->create(['number' => 'CN-HIDDEN']);

    $this->actingAs($user)
        ->get(route('credit-notes.index'))
        ->assertForbidden();
});

it('filters credit notes by status chip', function () {
    $user = User::factory()->owner()->create();

    CreditNote::factory()->create(['status' => 'issued', 'number' => 'CN-ISSUED']);
    CreditNote::factory()->create(['status' => 'applied', 'number' => 'CN-APPLIED']);

    Volt::actingAs($user)
        ->test('credit-notes.index')
        ->call('setStatusFilter', 'issued')
        ->assertSee('CN-ISSUED')
        ->assertDontSee('CN-APPLIED');
});

it('links credit note rows to the parent invoice show page', function () {
    $user = User::factory()->owner()->create();
    $invoice = Invoice::factory()->create(['number' => 'INV-PARENT-1']);
    CreditNote::factory()->create([
        'invoice_id' => $invoice->id,
        'number' => 'CN-LINK-1',
    ]);

    $this->actingAs($user)
        ->get(route('credit-notes.index'))
        ->assertOk()
        ->assertSee(route('invoices.show', $invoice), false);
});
