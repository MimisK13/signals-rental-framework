<?php

declare(strict_types=1);

namespace App\Domain\Crew\Database\Factories;

use App\Domain\Crew\Enums\CrewProficiency;
use App\Domain\Crew\Models\CrewMember;
use App\Domain\Crew\Models\CrewMemberSkill;
use App\Domain\Crew\Models\CrewSkill;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CrewMemberSkill> */
class CrewMemberSkillFactory extends Factory
{
    protected $model = CrewMemberSkill::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'crew_member_id' => CrewMember::factory(),
            'crew_skill_id' => CrewSkill::factory(),
            'proficiency' => CrewProficiency::Competent,
            'years_experience' => null,
            'verified_at' => null,
            'verified_by' => null,
        ];
    }

    public function verified(): static
    {
        return $this->state(fn (): array => ['verified_at' => now()]);
    }
}
