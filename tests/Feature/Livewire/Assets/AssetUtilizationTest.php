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

it('renders the asset utilization page with explicit period controls', function (): void {
    $product = Product::factory()->serialised()->create(['name' => 'Midas M32']);
    $asset = Asset::factory()->create([
        'product_id' => $product->id,
        'ownership_type' => AssetOwnershipType::CompanyOwned,
        'acquired_at' => '2026-01-01',
    ]);

    $this->get(route('assets.utilization', $asset))->assertOk();

    Volt::test('assets.utilization', ['asset' => $asset])
        ->assertSee('Actual Utilization')
        ->assertSee('From')
        ->assertSee('Until')
        ->assertSee('Plannable Time');
});
