<?php

use App\Domain\Administration\Database\Seeders\PermissionSeeder;
use App\Domain\Administration\Database\Seeders\RoleSeeder;
use App\Domain\Administration\Models\User;
use App\Domain\Products\Models\Product;
use App\Domain\Stock\Models\StockLevel;
use App\Domain\Stock\Stocktakes\Actions\StartStocktake;
use App\Domain\Stock\Stocktakes\Enums\StocktakeStatus;
use App\Domain\Stock\Stocktakes\Models\Stocktake;
use App\Domain\Stock\Stocktakes\Presentation\Livewire\Form;
use App\Domain\Stock\Stocktakes\Presentation\Livewire\Show;
use App\Domain\Stores\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

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
    Livewire::test(Form::class)
        ->set('storeId', $this->store->id)
        ->call('create')
        ->assertRedirect();

    $stocktake = Stocktake::query()->latest('id')->firstOrFail();

    Livewire::test(Show::class, ['stocktake' => $stocktake])
        ->call('start')
        ->assertSee('Bulk Cable')
        ->assertSee('12.00');

    expect($stocktake->fresh()->status)->toBe(StocktakeStatus::InProgress);
});

it('records a bulk count and shows the observation delta', function (): void {
    $stocktake = Stocktake::factory()->create(['store_id' => $this->store->id]);
    $stocktake = (new StartStocktake)($stocktake);
    $line = $stocktake->lines->firstOrFail();

    Livewire::test(Show::class, ['stocktake' => $stocktake])
        ->set("counts.{$line->id}", '9')
        ->call('recordCount', $line->id)
        ->assertSee('-3.00');
});
