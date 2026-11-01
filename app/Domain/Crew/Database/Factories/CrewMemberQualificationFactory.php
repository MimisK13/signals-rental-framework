<?php

declare(strict_types=1);

namespace App\Domain\Crew\Database\Factories;

use App\Domain\Crew\Models\CrewMember;
use App\Domain\Crew\Models\CrewMemberQualification;
use App\Domain\Crew\Models\CrewQualification;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CrewMemberQualification> */
class CrewMemberQualificationFactory extends Factory
{
    protected $model = CrewMemberQualification::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'crew_member_id' => CrewMember::factory(),
            'crew_qualification_id' => CrewQualification::factory(),
            'reference_number' => fake()->optional()->bothify('CERT-####'),
            'issued_at' => now()->subYear()->toDateString(),
            'expires_at' => now()->addYear()->toDateString(),
            'verified_at' => now(),
            'verified_by' => null,
            'notes' => null,
        ];
    }

    public function expiring(): static
    {
        return $this->state(fn (): array => [
            'expires_at' => now()->addDays(14)->toDateString(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'expires_at' => now()->subDay()->toDateString(),
        ]);
    }

    public function pendingVerification(): static
    {
        return $this->state(fn (): array => ['verified_at' => null]);
    }
}
