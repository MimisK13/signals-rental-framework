<?php

declare(strict_types=1);

use App\Domain\Activities\Models\Activity;
use App\Domain\Activities\Presentation\Livewire\Embedded\ProductActivities;
use App\Domain\Activities\Presentation\Livewire\Embedded\StockLevelActivities;
use App\Domain\Products\Models\Product;
use App\Domain\Stock\Models\StockLevel;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function (): void {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
});

it('renders product activities through the native domain panel', function (): void {
    $user = User::factory()->owner()->create();
    $product = Product::factory()->create();
    Activity::factory()->forProduct($product)->create(['subject' => 'Product activity domain integration']);

    $this->actingAs($user);
    Livewire::test(ProductActivities::class, ['product' => $product])
        ->assertOk()->assertSee('Product activity domain integration');
});

it('renders stock level activities through the native domain panel', function (): void {
    $user = User::factory()->owner()->create();
    $level = StockLevel::factory()->create();
    Activity::factory()->forStockLevel($level)->create(['subject' => 'Stock level activity domain integration']);

    $this->actingAs($user);
    Livewire::test(StockLevelActivities::class, ['stockLevel' => $level])
        ->assertOk()->assertSee('Stock level activity domain integration');
});
