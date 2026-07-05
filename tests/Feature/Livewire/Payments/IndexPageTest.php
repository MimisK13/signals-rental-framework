<?php

use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Livewire\Volt\Volt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
});

it('renders the payments index page for a permitted user', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['invoices.access', 'invoices.view']);

    $this->actingAs($user)
        ->get(route('payments.index'))
        ->assertOk()
        ->assertSee('Payments');
});

it('lists payments on the index page', function () {
    $payment = InvoicePayment::factory()->create([
        'reference' => 'PAY-LIST-1',
        'payment_method' => PaymentMethod::BankTransfer->value,
    ]);

    Volt::actingAs(User::factory()->owner()->create())
        ->test('payments.index')
        ->assertSee('PAY-LIST-1')
        ->assertSee($payment->invoice->number)
        ->assertSee('Bank transfer');
});

it('forbids the payments index page without invoices.view', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('invoices.access');

    InvoicePayment::factory()->create(['reference' => 'PAY-HIDDEN']);

    $this->actingAs($user)
        ->get(route('payments.index'))
        ->assertForbidden();
});

it('filters payments by payment method', function () {
    $user = User::factory()->owner()->create();

    InvoicePayment::factory()->create([
        'reference' => 'PAY-CARD-1',
        'payment_method' => PaymentMethod::Card->value,
    ]);
    InvoicePayment::factory()->create([
        'reference' => 'PAY-CASH-1',
        'payment_method' => PaymentMethod::Cash->value,
    ]);

    $this->actingAs($user)
        ->get(route('payments.index', ['filters' => ['payment_method' => PaymentMethod::Card->value]]))
        ->assertOk()
        ->assertSee('PAY-CARD-1')
        ->assertDontSee('PAY-CASH-1');
});

it('shows refund payments with a refund badge', function () {
    $user = User::factory()->owner()->create();
    $invoice = Invoice::factory()->create();

    InvoicePayment::factory()->create([
        'invoice_id' => $invoice->id,
        'reference' => 'REF-NEG-1',
        'amount' => -5000,
        'payment_method' => PaymentMethod::BankTransfer->value,
    ]);

    $this->actingAs($user)
        ->get(route('payments.index', ['filters' => ['payment_method' => PaymentMethod::BankTransfer->value]]))
        ->assertOk()
        ->assertSee('Refund')
        ->assertSee(route('invoices.show', $invoice), false);
});
