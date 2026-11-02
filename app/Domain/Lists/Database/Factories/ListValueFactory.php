<?php

declare(strict_types=1);

namespace App\Domain\Lists\Database\Factories;

use App\Domain\Lists\Models\ListName;
use App\Domain\Lists\Models\ListValue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ListValue>
 */
final class ListValueFactory extends Factory
{
    protected $model = ListValue::class;

    public function definition(): array
    {
        return [
            'list_name_id' => ListName::factory(),
            'name' => fake()->unique()->word(),
            'sort_order' => 0,
            'is_system' => false,
            'is_active' => true,
        ];
    }

    public function system(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_system' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function forList(ListName $listName): static
    {
        return $this->state(fn (array $attributes) => [
            'list_name_id' => $listName->id,
        ]);
    }
}
