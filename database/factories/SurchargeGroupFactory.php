<?php

namespace Database\Factories;

use App\Models\SurchargeGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SurchargeGroup>
 */
class SurchargeGroupFactory extends Factory
{
    protected $model = SurchargeGroup::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'is_default' => false,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function defaultGroup(): static
    {
        return $this->state(fn (): array => [
            'is_default' => true,
        ]);
    }
}
