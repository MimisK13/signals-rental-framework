<?php

use App\Domain\Administration\Models\User;
use App\Domain\Products\Models\Product;
use App\Domain\Stock\Models\StockLevel;
use App\Domain\Stores\Models\Store;
use App\Livewire\Components\DataTable;
use Livewire\Livewire;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->user = User::factory()->owner()->create();
    $this->actingAs($this->user);
});

it('renders the product stock tab', function () {
    $product = Product::factory()->create(['name' => 'Chauvet COLORado Panel Q40']);

    $this->get(route('products.stock', $product))
        ->assertOk()
        ->assertSee('Stock');
});

it('links the item name to the stock level show page', function () {
    $product = Product::factory()->create(['name' => 'Chauvet COLORado Panel Q40']);
    $store = Store::factory()->create();
    $stockLevel = StockLevel::factory()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'item_name' => 'Chauvet COLORado Panel Q40',
    ]);

    Livewire::test(DataTable::class, [
        'columns' => [
            ['key' => 'item_name', 'label' => 'Item Name', 'sortable' => true, 'view' => 'products::partials.stock-item-name'],
        ],
        'model' => StockLevel::class,
        'scopes' => ['forProduct' => $product->id],
    ])
        ->assertSee('Chauvet COLORado Panel Q40')
        ->assertSee(route('stock-levels.show', $stockLevel), false)
        ->assertSee('<a', false);
});
