<?php

declare(strict_types=1);

namespace App\Domain\Crew\Database\Factories;

use App\Domain\Crew\Models\CrewSkill;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<CrewSkill> */
class CrewSkillFactory extends Factory
{
    protected $model = CrewSkill::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'category' => fake()->randomElement(['audio', 'rigging', 'video', 'lighting', 'general']),
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }
}
