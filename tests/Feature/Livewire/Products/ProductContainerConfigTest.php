<?php

use App\Enums\StockMethod;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Livewire\Volt\Volt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
    $this->product = Product::factory()->serialised()->create();
    $this->component = Product::factory()->serialised()->create();
});

it('shows the container config tab for serialised products only', function () {
    $bulk = Product::factory()->create(['stock_method' => StockMethod::Bulk]);

    $this->actingAs($this->owner)
        ->get(route('products.show', $this->product))
        ->assertOk()
        ->assertSee('Container Config');

    $this->actingAs($this->owner)
        ->get(route('products.show', $bulk))
        ->assertOk()
        ->assertDontSee('Container Config');
});

it('forbids the container config page for bulk products', function () {
    $bulk = Product::factory()->create(['stock_method' => StockMethod::Bulk]);

    $this->actingAs($this->owner)
        ->get(route('products.container-config', $bulk))
        ->assertNotFound();
});

it('saves container configuration via the tab', function () {
    $this->actingAs($this->owner);

    Volt::test('products.container-config', ['product' => $this->product])
        ->set('isContainerable', true)
        ->set('availabilityMode', 'kit')
        ->set('templateSlots', [[
            'product_id' => $this->component->id,
            'product_name' => $this->component->name,
            'quantity' => 2,
            'binding' => 'fixed',
        ]])
        ->call('save')
        ->assertHasNoErrors();

    $this->product->refresh();

    expect($this->product->is_containerable)->toBeTrue()
        ->and($this->product->container_template['slots'][0]['product_id'])->toBe($this->component->id);
});

it('hides save controls without manage_templates permission', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['products.view', 'products.edit', 'stock.access', 'containers.view']);

    $this->actingAs($user);

    Volt::test('products.container-config', ['product' => $this->product])
        ->assertSee('Manage Container Templates')
        ->assertDontSee('Save Container Config');
});

it('shows container summary on the product overview', function () {
    $this->product->update([
        'is_containerable' => true,
        'container_availability_mode' => 'kit',
    ]);

    $this->actingAs($this->owner)
        ->get(route('products.show', $this->product))
        ->assertOk()
        ->assertSee('Container Configuration')
        ->assertSee('Kit');
});
