<?php

declare(strict_types=1);

namespace App\Domain\Crew\Database\Factories;

use App\Domain\Crew\Models\CrewQualification;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<CrewQualification> */
class CrewQualificationFactory extends Factory
{
    protected $model = CrewQualification::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->bothify('Certification ###');

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'issuer' => fake()->optional()->company(),
            'description' => fake()->optional()->sentence(),
            'validity_months' => null,
            'requires_expiry' => false,
            'is_mandatory' => false,
            'is_active' => true,
        ];
    }

    public function expiring(): static
    {
        return $this->state(fn (): array => [
            'validity_months' => 12,
            'requires_expiry' => true,
        ]);
    }

    public function mandatory(): static
    {
        return $this->state(fn (): array => ['is_mandatory' => true]);
    }
}
