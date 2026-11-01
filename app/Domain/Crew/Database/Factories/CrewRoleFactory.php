<?php

declare(strict_types=1);

namespace App\Domain\Crew\Database\Factories;

use App\Domain\Crew\Models\CrewRole;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<CrewRole> */
class CrewRoleFactory extends Factory
{
    protected $model = CrewRole::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->jobTitle();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->optional()->sentence(),
            'is_supervisory' => false,
            'requires_certification' => false,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function supervisory(): static
    {
        return $this->state(fn (): array => ['is_supervisory' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
