<?php

declare(strict_types=1);

namespace App\Domain\CustomFields\Database\Factories;

use App\Domain\CustomFields\Models\CustomFieldGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomFieldGroup>
 */
class CustomFieldGroupFactory extends Factory
{
    protected $model = CustomFieldGroup::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'sort_order' => 0,
        ];
    }

    public function plugin(string $name = 'test-plugin'): static
    {
        return $this->state(fn () => ['plugin_name' => $name]);
    }
}
