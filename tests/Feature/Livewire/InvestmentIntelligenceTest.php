<?php

use App\Domain\Assets\Enums\AssetOwnershipType;
use App\Domain\Assets\Models\Asset;
use App\Domain\Assets\Presentation\Livewire\Investment;
use App\Domain\Products\Models\Product;
use App\Models\User;
use Livewire\Livewire;

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

    Livewire::test(Investment::class, ['asset' => $asset])
        ->assertSee('Investment Position')
        ->assertSee('Operating Evidence')
        ->assertSee('Signals &amp; Guidance', false);
});
