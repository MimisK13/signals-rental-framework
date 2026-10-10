<?php

namespace Database\Factories;

use App\Enums\StocktakeStatus;
use App\Models\Stocktake;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Stocktake> */
class StocktakeFactory extends Factory
{
    protected $model = Stocktake::class;

    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'status' => StocktakeStatus::Draft,
        ];
    }
}
