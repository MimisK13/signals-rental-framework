<?php

declare(strict_types=1);

namespace App\Domain\Crew\Database\Factories;

use App\Domain\Crew\Models\CrewShift;
use App\Domain\Crew\Models\CrewShiftBreak;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CrewShiftBreak> */
class CrewShiftBreakFactory extends Factory
{
    protected $model = CrewShiftBreak::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'crew_shift_id' => CrewShift::factory(),
            'starts_at' => now()->addWeek()->setTime(12, 0),
            'ends_at' => now()->addWeek()->setTime(12, 30),
            'is_paid' => false,
            'label' => 'Meal',
        ];
    }
}
