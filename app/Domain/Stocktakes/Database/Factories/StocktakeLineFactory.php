<?php

declare(strict_types=1);

namespace App\Domain\Stocktakes\Database\Factories;

use App\Domain\Stocktakes\Models\Stocktake;
use App\Domain\Stocktakes\Models\StocktakeLine;
use App\Models\StockLevel;
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
