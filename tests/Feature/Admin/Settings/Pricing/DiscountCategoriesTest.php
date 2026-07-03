<?php

use App\Models\DiscountCategory;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->actingAs(User::factory()->owner()->create());
});

it('renders the discount categories page', function () {
    $this->get(route('admin.settings.pricing.discount-categories'))
        ->assertOk()
        ->assertSee('Discount Categories');
});

it('lists discount categories', function () {
    DiscountCategory::factory()->create(['name' => 'Trade']);
    DiscountCategory::factory()->create(['name' => 'Staff']);

    Volt::test('admin.settings.pricing.discount-categories')
        ->assertSee('Trade')
        ->assertSee('Staff');
});

it('renders the create form', function () {
    $this->get(route('admin.settings.pricing.discount-categories.create'))
        ->assertOk()
        ->assertSee('Create Discount Category');
});

it('can create a discount category', function () {
    Volt::test('admin.settings.pricing.discount-category-form')
        ->set('name', 'Preferred')
        ->set('description', 'Preferred customer band')
        ->call('save');

    expect(DiscountCategory::where('name', 'Preferred')->exists())->toBeTrue();
});

it('can edit a discount category', function () {
    $category = DiscountCategory::factory()->create(['name' => 'Old Name']);

    Volt::test('admin.settings.pricing.discount-category-form', ['discountCategory' => $category])
        ->assertSet('name', 'Old Name')
        ->set('name', 'New Name')
        ->call('save');

    expect($category->fresh()->name)->toBe('New Name');
});

it('can delete a discount category', function () {
    $category = DiscountCategory::factory()->create(['name' => 'To Delete']);

    Volt::test('admin.settings.pricing.discount-categories')
        ->call('deleteCategory', $category->id);

    expect(DiscountCategory::find($category->id))->toBeNull();
});

it('validates required name', function () {
    Volt::test('admin.settings.pricing.discount-category-form')
        ->set('name', '')
        ->call('save')
        ->assertHasErrors(['name']);
});

it('returns 403 for non-admin users', function () {
    $regularUser = User::factory()->create();

    $this->actingAs($regularUser)
        ->get(route('admin.settings.pricing.discount-categories'))
        ->assertForbidden();
});
