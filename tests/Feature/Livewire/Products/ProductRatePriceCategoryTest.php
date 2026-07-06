<?php

use App\Models\PriceCategory;
use App\Models\Product;
use App\Models\ProductRate;
use App\Models\RateDefinition;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Livewire\Volt\Volt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
    $this->actingAs($this->owner);
    $this->product = Product::factory()->create();
    $this->definition = RateDefinition::factory()->create(['name' => 'Daily Rate']);
    $this->trade = PriceCategory::factory()->create(['name' => 'Trade']);
});

it('shows the price category selector on the assign rate form', function () {
    $this->get(route('products.rates.create', $this->product))
        ->assertOk()
        ->assertSee('Price Category')
        ->assertSee('Default (all categories)');
});

it('creates a rate scoped to a price category from the editor', function () {
    Volt::test('products.rate-form', ['product' => $this->product])
        ->set('rateDefinitionId', $this->definition->id)
        ->set('transactionType', 'rental')
        ->set('price', '88.00')
        ->set('currency', 'GBP')
        ->set('priceCategoryId', $this->trade->id)
        ->set('priority', 0)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('products.rates', $this->product));

    $this->assertDatabaseHas('product_rates', [
        'product_id' => $this->product->id,
        'rate_definition_id' => $this->definition->id,
        'price_category_id' => $this->trade->id,
        'price' => 8800,
    ]);
});

it('populates the price category when editing an existing rate', function () {
    $rate = ProductRate::factory()->forPriceCategory($this->trade->id)->create([
        'product_id' => $this->product->id,
        'rate_definition_id' => $this->definition->id,
        'price' => 5000,
        'currency' => 'GBP',
    ]);

    Volt::test('products.rate-form', ['product' => $this->product, 'rate' => $rate])
        ->assertSet('priceCategoryId', $this->trade->id);
});

it('lists the price category on the product rates tab', function () {
    ProductRate::factory()->forPriceCategory($this->trade->id)->create([
        'product_id' => $this->product->id,
        'rate_definition_id' => $this->definition->id,
    ]);

    $this->get(route('products.rates', $this->product))
        ->assertOk()
        ->assertSee('Price Category')
        ->assertSee('Trade');
});
