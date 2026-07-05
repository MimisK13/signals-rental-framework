<?php

use App\Models\DiscountCategory;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->actingAs(User::factory()->owner()->create());
});

it('can create a discount category with rules from the form', function () {
    $group = ProductGroup::factory()->create(['name' => 'Lighting']);
    $product = Product::factory()->create(['name' => 'PAR Can']);

    Volt::test('admin.settings.pricing.discount-category-form')
        ->set('name', 'Trade')
        ->set('rules', [[
            'discount_percent' => '15.00',
            'product_group_ids' => [$group->id],
            'tag_names' => 'lighting, led',
            'product_ids' => [$product->id],
            'transaction_types' => ['rental'],
            'valid_from' => '',
            'valid_to' => '',
            'sort_order' => 0,
        ]])
        ->call('save');

    $category = DiscountCategory::where('name', 'Trade')->first();
    expect($category)->not->toBeNull();
    expect($category->rules)->toHaveCount(1);
    expect((string) $category->rules->first()->discount_percent)->toBe('15.00');
    expect($category->rules->first()->criteria)->toMatchArray([
        'product_group_ids' => [$group->id],
        'product_ids' => [$product->id],
        'tag_names' => ['lighting', 'led'],
        'transaction_types' => ['rental'],
    ]);
});

it('can edit rules on an existing discount category', function () {
    $category = DiscountCategory::factory()->create(['name' => 'Editable']);
    $category->rules()->create([
        'discount_percent' => '5.00',
        'criteria' => [],
        'sort_order' => 0,
    ]);

    Volt::test('admin.settings.pricing.discount-category-form', ['discountCategory' => $category])
        ->set('rules.0.discount_percent', '22.50')
        ->call('save');

    expect((string) $category->fresh()->rules->first()->discount_percent)->toBe('22.50');
});

it('can add and remove rules on the form', function () {
    Volt::test('admin.settings.pricing.discount-category-form')
        ->call('addRule')
        ->assertCount('rules', 1)
        ->call('removeRule', 0)
        ->assertCount('rules', 0);
});

it('loads existing rules when editing', function () {
    $category = DiscountCategory::factory()->create(['name' => 'With Rules']);
    $category->rules()->create([
        'discount_percent' => '10.00',
        'criteria' => ['tag_names' => ['lighting']],
        'sort_order' => 0,
    ]);

    Volt::test('admin.settings.pricing.discount-category-form', ['discountCategory' => $category])
        ->assertSet('rules.0.discount_percent', '10.00')
        ->assertSet('rules.0.tag_names', 'lighting');
});
