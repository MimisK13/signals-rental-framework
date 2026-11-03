<?php

declare(strict_types=1);

namespace App\Domain\Stock\Stocktakes\Database\Factories;

use App\Domain\Stock\Stocktakes\Enums\StocktakeStatus;
use App\Domain\Stock\Stocktakes\Models\Stocktake;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Stocktake> */
final class StocktakeFactory extends Factory
{
    protected $model = Stocktake::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'status' => StocktakeStatus::Draft,
        ];
    }
}
