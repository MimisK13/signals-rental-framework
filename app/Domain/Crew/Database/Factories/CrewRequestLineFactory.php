<?php

declare(strict_types=1);

namespace App\Domain\Crew\Database\Factories;

use App\Domain\Crew\Models\CrewRequest;
use App\Domain\Crew\Models\CrewRequestLine;
use App\Domain\Crew\Models\CrewRole;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CrewRequestLine> */
class CrewRequestLineFactory extends Factory
{
    protected $model = CrewRequestLine::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'crew_request_id' => CrewRequest::factory(),
            'crew_role_id' => CrewRole::factory(),
            'quantity' => 1,
            'filled_quantity' => 0,
            'starts_at' => null,
            'ends_at' => null,
            'min_proficiency' => null,
            'notes' => null,
            'sort_order' => 0,
        ];
    }
}
