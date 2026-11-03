<?php

declare(strict_types=1);

use App\Domain\Administration\Models\User;
use App\Domain\Products\Models\Product;
use App\Domain\Products\Presentation\Livewire\Investment;
use Livewire\Livewire;

beforeEach(function (): void {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->actingAs(User::factory()->owner()->create());
});

it('renders the product investment page only for serialized products', function (): void {
    $product = Product::factory()->serialised()->create(['name' => 'Midas M32']);

    $this->get(route('products.investment', $product))->assertOk();

    Livewire::test(Investment::class, ['product' => $product])
        ->assertSee('Financial Position')
        ->assertSee('Fleet Capacity')
        ->assertSee('Signals &amp; Guidance', false);
});

it('does not expose product investment for bulk products', function (): void {
    $product = Product::factory()->bulk()->create();

    $this->get(route('products.investment', $product))->assertNotFound();
});
