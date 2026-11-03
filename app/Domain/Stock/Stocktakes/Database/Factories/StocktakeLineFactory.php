<?php

declare(strict_types=1);

namespace App\Domain\Stock\Stocktakes\Database\Factories;

use App\Domain\Stock\Models\StockLevel;
use App\Domain\Stock\Stocktakes\Models\Stocktake;
use App\Domain\Stock\Stocktakes\Models\StocktakeLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StocktakeLine> */
final class StocktakeLineFactory extends Factory
{
    protected $model = StocktakeLine::class;

    /** @return array<string, mixed> */
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
