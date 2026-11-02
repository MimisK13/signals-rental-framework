<?php

use App\Domain\Tax\Models\ProductTaxClass;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->user = User::factory()->owner()->create();
    $this->actingAs($this->user);
});

it('renders the product tax classes page', function () {
    $this->get(route('admin.settings.tax.product-tax-classes'))
        ->assertOk()
        ->assertSee('Product Tax Classes');
});

it('lists product tax classes', function () {
    ProductTaxClass::factory()->create(['name' => 'Standard Rate']);
    ProductTaxClass::factory()->create(['name' => 'Reduced Rate']);

    Livewire::test('admin.settings.tax.product-tax-classes')
        ->assertSee('Standard Rate')
        ->assertSee('Reduced Rate');
});

it('renders the create form', function () {
    $this->get(route('admin.settings.tax.product-tax-classes.create'))
        ->assertOk()
        ->assertSee('Create Product Tax Class');
});

it('can create a tax class', function () {
    Livewire::test('admin.settings.tax.product-tax-class-form')
        ->set('name', 'Zero Rate')
        ->set('description', 'Zero rated items')
        ->call('save');

    expect(ProductTaxClass::where('name', 'Zero Rate')->exists())->toBeTrue();
});

it('renders the edit form', function () {
    $taxClass = ProductTaxClass::factory()->create(['name' => 'Editable Class']);

    $this->get(route('admin.settings.tax.product-tax-classes.edit', $taxClass))
        ->assertOk()
        ->assertSee('Edit Product Tax Class');
});

it('can edit a tax class', function () {
    $taxClass = ProductTaxClass::factory()->create(['name' => 'Old Name']);

    Livewire::test('admin.settings.tax.product-tax-class-form', ['productTaxClass' => $taxClass])
        ->assertSet('name', 'Old Name')
        ->set('name', 'New Name')
        ->call('save');

    expect($taxClass->fresh()->name)->toBe('New Name');
});

it('can set default', function () {
    $taxClass1 = ProductTaxClass::factory()->default()->create(['name' => 'Class A']);
    $taxClass2 = ProductTaxClass::factory()->create(['name' => 'Class B']);

    Livewire::test('admin.settings.tax.product-tax-classes')
        ->call('setDefault', $taxClass2->id);

    expect($taxClass1->fresh()->is_default)->toBeFalse();
    expect($taxClass2->fresh()->is_default)->toBeTrue();
});

it('cannot delete the default tax class', function () {
    $taxClass = ProductTaxClass::factory()->default()->create(['name' => 'Default Class']);

    Livewire::test('admin.settings.tax.product-tax-classes')
        ->call('deleteTaxClass', $taxClass->id)
        ->assertHasErrors('deleteTaxClass');

    expect(ProductTaxClass::where('name', 'Default Class')->exists())->toBeTrue();
});

it('can delete a non-default tax class', function () {
    ProductTaxClass::factory()->default()->create();
    $taxClass = ProductTaxClass::factory()->create(['name' => 'To Delete']);

    Livewire::test('admin.settings.tax.product-tax-classes')
        ->call('deleteTaxClass', $taxClass->id);

    expect(ProductTaxClass::where('name', 'To Delete')->exists())->toBeFalse();
});

it('validates required name', function () {
    Livewire::test('admin.settings.tax.product-tax-class-form')
        ->set('name', '')
        ->call('save')
        ->assertHasErrors(['name']);
});

it('returns 403 for non-admin users', function () {
    $regularUser = User::factory()->create();

    $this->actingAs($regularUser)
        ->get(route('admin.settings.tax.product-tax-classes'))
        ->assertForbidden();
});
