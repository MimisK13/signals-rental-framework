<?php

declare(strict_types=1);

namespace App\Domain\Crew\Database\Factories;

use App\Domain\Crew\Enums\CrewRequirementType;
use App\Domain\Crew\Models\CrewRequestLine;
use App\Domain\Crew\Models\CrewRequestLineRequirement;
use App\Domain\Crew\Models\CrewSkill;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CrewRequestLineRequirement> */
class CrewRequestLineRequirementFactory extends Factory
{
    protected $model = CrewRequestLineRequirement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'crew_request_line_id' => CrewRequestLine::factory(),
            'requirement_type' => CrewRequirementType::Skill,
            'crew_skill_id' => CrewSkill::factory(),
            'crew_qualification_id' => null,
            'is_mandatory' => true,
            'min_proficiency' => null,
        ];
    }
}
