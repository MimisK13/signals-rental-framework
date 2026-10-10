<?php

namespace Database\Factories;

use App\Models\StockLevel;
use App\Models\Stocktake;
use App\Models\StocktakeLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StocktakeLine> */
class StocktakeLineFactory extends Factory
{
    protected $model = StocktakeLine::class;

    public function definition(): array
    {
        $stockLevel = StockLevel::factory()->create();

        return [
            'stocktake_id' => Stocktake::factory(),
            'stock_level_id' => $stockLevel->id,
            'product_name_snapshot' => $stockLevel->product->name ?? 'Stock item',
            'stock_level_label_snapshot' => $stockLevel->item_name,
            'quantity_held_snapshot' => $stockLevel->quantity_held,
        ];
    }
}
