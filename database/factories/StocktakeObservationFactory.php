<?php

namespace Database\Factories;

use App\Models\StocktakeLine;
use App\Models\StocktakeObservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StocktakeObservation> */
class StocktakeObservationFactory extends Factory
{
    protected $model = StocktakeObservation::class;

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
