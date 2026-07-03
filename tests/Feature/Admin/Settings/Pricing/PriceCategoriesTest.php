<?php

use App\Models\PriceCategory;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->actingAs(User::factory()->owner()->create());
});

it('renders the price categories page', function () {
    $this->get(route('admin.settings.pricing.price-categories'))
        ->assertOk()
        ->assertSee('Price Categories');
});

it('lists price categories', function () {
    PriceCategory::factory()->create(['name' => 'Retail']);
    PriceCategory::factory()->create(['name' => 'Trade']);

    Volt::test('admin.settings.pricing.price-categories')
        ->assertSee('Retail')
        ->assertSee('Trade');
});

it('renders the create form', function () {
    $this->get(route('admin.settings.pricing.price-categories.create'))
        ->assertOk()
        ->assertSee('Create Price Category');
});

it('can create a price category with tiers', function () {
    Volt::test('admin.settings.pricing.price-category-form')
        ->set('name', 'Retail')
        ->set('tiers', [
            ['tier_number' => 1, 'label' => 'Day 1', 'multiplier' => '1.0000', 'sort_order' => 0],
            ['tier_number' => 2, 'label' => 'Day 2+', 'multiplier' => '0.7500', 'sort_order' => 1],
        ])
        ->call('save');

    $category = PriceCategory::where('name', 'Retail')->first();
    expect($category)->not->toBeNull();
    expect($category->tiers)->toHaveCount(2);
});

it('can edit a price category', function () {
    $category = PriceCategory::factory()->withDefaultTiers()->create(['name' => 'Old Name']);

    Volt::test('admin.settings.pricing.price-category-form', ['priceCategory' => $category])
        ->assertSet('name', 'Old Name')
        ->set('name', 'New Name')
        ->call('save');

    expect($category->fresh()->name)->toBe('New Name');
});

it('can add and remove tiers on the form', function () {
    Volt::test('admin.settings.pricing.price-category-form')
        ->call('addTier')
        ->assertCount('tiers', 2)
        ->call('removeTier', 1)
        ->assertCount('tiers', 1);
});

it('can delete a price category', function () {
    $category = PriceCategory::factory()->create(['name' => 'To Delete']);

    Volt::test('admin.settings.pricing.price-categories')
        ->call('deleteCategory', $category->id);

    expect(PriceCategory::where('name', 'To Delete')->exists())->toBeFalse();
});

it('returns 403 for non-admin users', function () {
    $regularUser = User::factory()->create();

    $this->actingAs($regularUser)
        ->get(route('admin.settings.pricing.price-categories'))
        ->assertForbidden();
});
