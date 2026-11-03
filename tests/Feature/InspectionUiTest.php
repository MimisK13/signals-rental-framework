<?php

use App\Domain\Administration\Models\User;
use App\Domain\Assets\Models\Asset;
use App\Domain\Assets\Models\InspectionPolicy;
use App\Domain\Products\Models\Product;

beforeEach(function (): void {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
});

it('shows inspection policies on the Product inspections page', function (): void {
    $user = User::factory()->owner()->create();
    $product = Product::factory()->serialised()->create(['name' => 'CM Lodestar D8+ 1T']);
    InspectionPolicy::factory()->create([
        'product_id' => $product->id,
        'name' => 'Annual lifting inspection',
    ]);

    $this->actingAs($user)
        ->get(route('products.inspections', $product))
        ->assertSuccessful()
        ->assertSee('Annual lifting inspection')
        ->assertSee('Inspection Policies');
});

it('shows inspection history on the Asset detail page', function (): void {
    $user = User::factory()->owner()->create();
    $product = Product::factory()->serialised()->create();
    $asset = Asset::factory()->create(['product_id' => $product->id]);
    InspectionPolicy::factory()->create(['product_id' => $product->id, 'name' => 'Electrical safety']);

    $this->actingAs($user)
        ->get(route('assets.show', $asset))
        ->assertSuccessful()
        ->assertSee('Inspections')
        ->assertSee('Electrical safety');
});
