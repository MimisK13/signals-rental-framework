<?php

use App\Models\Product;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function (): void {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->actingAs(User::factory()->owner()->create());
});

it('renders the product utilization page for serialized products', function (): void {
    $product = Product::factory()->serialised()->create(['name' => 'Midas M32']);

    $this->get(route('products.utilization', $product))->assertOk();

    Volt::test('products.utilization', ['product' => $product])
        ->assertSee('Weighted Fleet Utilization')
        ->assertSee('From')
        ->assertSee('Until');
});

it('does not expose utilization for bulk products', function (): void {
    $product = Product::factory()->bulk()->create();

    $this->get(route('products.utilization', $product))->assertNotFound();
});
