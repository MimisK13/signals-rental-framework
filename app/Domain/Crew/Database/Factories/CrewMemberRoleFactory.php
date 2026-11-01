<?php

declare(strict_types=1);

namespace App\Domain\Crew\Database\Factories;

use App\Domain\Crew\Enums\CrewProficiency;
use App\Domain\Crew\Models\CrewMember;
use App\Domain\Crew\Models\CrewMemberRole;
use App\Domain\Crew\Models\CrewRole;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CrewMemberRole> */
class CrewMemberRoleFactory extends Factory
{
    protected $model = CrewMemberRole::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'crew_member_id' => CrewMember::factory(),
            'crew_role_id' => CrewRole::factory(),
            'proficiency' => CrewProficiency::Competent,
            'is_primary' => false,
            'years_experience' => null,
        ];
    }

    public function primary(): static
    {
        return $this->state(fn (): array => ['is_primary' => true]);
    }
}
