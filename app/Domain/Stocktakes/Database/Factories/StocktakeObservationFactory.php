<?php

declare(strict_types=1);

namespace App\Domain\Stocktakes\Database\Factories;

use App\Domain\Stocktakes\Models\StocktakeLine;
use App\Domain\Stocktakes\Models\StocktakeObservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StocktakeObservation> */
final class StocktakeObservationFactory extends Factory
{
    protected $model = StocktakeObservation::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $line = StocktakeLine::factory()->create();

        return [
            'stocktake_id' => $line->stocktake_id,
            'stocktake_line_id' => $line->id,
            'counted_quantity' => $line->quantity_held_snapshot,
            'counted_by_user_id' => User::factory(),
            'counted_at' => now('UTC'),
        ];
    }
}
