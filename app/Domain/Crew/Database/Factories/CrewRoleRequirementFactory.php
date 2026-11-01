<?php

declare(strict_types=1);

namespace App\Domain\Crew\Database\Factories;

use App\Domain\Crew\Enums\CrewRequirementType;
use App\Domain\Crew\Models\CrewRole;
use App\Domain\Crew\Models\CrewRoleRequirement;
use App\Domain\Crew\Models\CrewSkill;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CrewRoleRequirement> */
class CrewRoleRequirementFactory extends Factory
{
    protected $model = CrewRoleRequirement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'crew_role_id' => CrewRole::factory(),
            'requirement_type' => CrewRequirementType::Skill,
            'crew_skill_id' => CrewSkill::factory(),
            'crew_qualification_id' => null,
            'is_mandatory' => true,
            'min_proficiency' => null,
            'min_years_experience' => null,
        ];
    }
}
