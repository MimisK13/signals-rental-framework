<?php

declare(strict_types=1);

namespace App\Domain\Crew\Database\Factories;

use App\Domain\Administration\Models\User;
use App\Domain\Crew\Enums\CrewEngagementType;
use App\Domain\Crew\Enums\CrewMemberStatus;
use App\Domain\Crew\Models\CrewMember;
use App\Domain\Members\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CrewMember> */
class CrewMemberFactory extends Factory
{
    protected $model = CrewMember::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'member_id' => null,
            'user_id' => null,
            'supplier_member_id' => null,
            'engagement_type' => CrewEngagementType::Employee,
            'status' => CrewMemberStatus::Active,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->optional()->phoneNumber(),
            'job_title' => fake()->optional()->jobTitle(),
            'home_timezone' => 'UTC',
            'home_postcode' => fake()->optional()->postcode(),
            'travel_radius_km' => null,
            'internal_notes' => null,
            'external_reference' => null,
            'starts_on' => null,
            'ends_on' => null,
            'last_assigned_at' => null,
        ];
    }

    public function employee(): static
    {
        return $this->state(fn (): array => ['engagement_type' => CrewEngagementType::Employee]);
    }

    public function freelancer(): static
    {
        return $this->state(fn (): array => ['engagement_type' => CrewEngagementType::Freelancer]);
    }

    public function contractor(): static
    {
        return $this->state(fn (): array => ['engagement_type' => CrewEngagementType::Contractor]);
    }

    public function externalCrew(): static
    {
        return $this->state(fn (): array => ['engagement_type' => CrewEngagementType::ExternalCrew]);
    }

    public function active(): static
    {
        return $this->state(fn (): array => ['status' => CrewMemberStatus::Active]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['status' => CrewMemberStatus::Inactive]);
    }

    public function archived(): static
    {
        return $this->state(fn (): array => ['status' => CrewMemberStatus::Archived]);
    }

    public function withUser(): static
    {
        return $this->state(fn (): array => ['user_id' => User::factory()]);
    }

    public function withoutUser(): static
    {
        return $this->state(fn (): array => ['user_id' => null]);
    }

    public function withMember(): static
    {
        return $this->state(fn (): array => ['member_id' => Member::factory()]);
    }
}
