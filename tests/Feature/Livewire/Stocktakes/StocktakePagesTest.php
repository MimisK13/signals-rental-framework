<?php

use App\Actions\Stocktakes\StartStocktake;
use App\Enums\StocktakeStatus;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Stocktake;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->user = User::factory()->owner()->create();
    $this->actingAs($this->user);
    $this->store = Store::factory()->create();
    $this->product = Product::factory()->bulk()->create(['name' => 'Bulk Cable']);
    $this->stockLevel = StockLevel::factory()->bulk()->create([
        'store_id' => $this->store->id,
        'product_id' => $this->product->id,
        'quantity_held' => 12,
    ]);
});

it('creates and starts a stocktake from the operator pages', function (): void {
    Volt::test('stocktakes.form')
        ->set('storeId', $this->store->id)
        ->call('create')
        ->assertRedirect();

    $stocktake = Stocktake::query()->latest('id')->firstOrFail();

    Volt::test('stocktakes.show', ['stocktake' => $stocktake])
        ->call('start')
        ->assertSee('Bulk Cable')
        ->assertSee('12.00');

    expect($stocktake->fresh()->status)->toBe(StocktakeStatus::InProgress);
});

it('records a bulk count and shows the observation delta', function (): void {
    $stocktake = Stocktake::factory()->create(['store_id' => $this->store->id]);
    $stocktake = (new StartStocktake)($stocktake);
    $line = $stocktake->lines->firstOrFail();

    Volt::test('stocktakes.show', ['stocktake' => $stocktake])
        ->set("counts.{$line->id}", '9')
        ->call('recordCount', $line->id)
        ->assertSee('-3.00');
});
