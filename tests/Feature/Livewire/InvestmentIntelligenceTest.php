<?php

use App\Enums\AssetOwnershipType;
use App\Models\Asset;
use App\Models\Product;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function (): void {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->actingAs(User::factory()->owner()->create());
});

it('renders the asset investment page with explainable sections', function (): void {
    $product = Product::factory()->serialised()->create(['name' => 'Midas M32']);
    $asset = Asset::factory()->create([
        'product_id' => $product->id,
        'ownership_type' => AssetOwnershipType::CompanyOwned,
        'acquired_at' => '2025-01-01',
        'base_currency_code' => 'GBP',
        'base_acquisition_amount' => 320000,
    ]);

    $this->get(route('assets.investment', $asset))->assertOk();

    Volt::test('assets.investment', ['asset' => $asset])
        ->assertSee('Investment Position')
        ->assertSee('Operating Evidence')
        ->assertSee('Signals &amp; Guidance', false);
});

it('renders the product investment page only for serialized products', function (): void {
    $product = Product::factory()->serialised()->create(['name' => 'Midas M32']);

    $this->get(route('products.investment', $product))->assertOk();

    Volt::test('products.investment', ['product' => $product])
        ->assertSee('Financial Position')
        ->assertSee('Fleet Capacity')
        ->assertSee('Signals &amp; Guidance', false);
});

it('does not expose product investment for bulk products', function (): void {
    $product = Product::factory()->bulk()->create();

    $this->get(route('products.investment', $product))->assertNotFound();
});
