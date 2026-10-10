<?php

use App\Models\Member;
use App\Models\SupplierBill;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Livewire\Volt\Volt;

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $user = User::factory()->owner()->create();
    $user->givePermissionTo([
        'costs.view', 'supplier_bills.view', 'supplier_bills.create', 'supplier_bills.edit',
        'supplier_bills.issue', 'supplier_bills.void',
    ]);
    $this->actingAs($user);
});

it('lists and shows supplier bills', function (): void {
    $bill = SupplierBill::factory()->create(['document_number' => 'SB-UI-1']);

    $this->get(route('supplier-bills.index'))->assertOk()->assertSee('Supplier Bills')->assertSee('SB-UI-1');
    Volt::test('supplier-bills.show', ['supplierBill' => $bill])->assertSee('SB-UI-1')->assertSee('Supplier');
});

it('creates a draft supplier bill from the UI form', function (): void {
    $supplier = Member::factory()->organisation()->create();

    Volt::test('supplier-bills.form')
        ->set('supplierId', $supplier->id)
        ->set('documentNumber', 'SB-UI-2')
        ->set('currencyCode', 'EUR')
        ->set('lines.0.description', 'External M32')
        ->set('lines.0.quantity', '1')
        ->set('lines.0.unit_cost', '280.00')
        ->call('save')
        ->assertHasNoErrors();

    expect(SupplierBill::query()->where('document_number', 'SB-UI-2')->firstOrFail()->statusEnum()->value)->toBe('draft');
});
