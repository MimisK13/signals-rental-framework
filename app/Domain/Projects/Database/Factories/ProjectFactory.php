<?php

declare(strict_types=1);

namespace App\Domain\Projects\Database\Factories;

use App\Domain\Projects\Enums\ProjectStatus;
use App\Domain\Projects\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Project> */
final class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        return [
            'name' => fake()->sentence(3),
            'status' => ProjectStatus::Draft,
            'starts_at' => null,
            'ends_at' => null,
        ];
    }
}
