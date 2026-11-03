<?php

declare(strict_types=1);

namespace App\Domain\ReferenceData\Database\Factories;

use App\Domain\ReferenceData\Models\CostGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CostGroup>
 */
class CostGroupFactory extends Factory
{
    protected $model = CostGroup::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
