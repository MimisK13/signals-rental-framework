<?php

declare(strict_types=1);

namespace App\Domain\CustomViews\Database\Factories;

use App\Domain\Administration\Models\User;
use App\Domain\CustomViews\Models\CustomView;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomView>
 */
final class CustomViewFactory extends Factory
{
    protected $model = CustomView::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'entity_type' => 'members',
            'visibility' => 'personal',
            'user_id' => User::factory(),
            'is_default' => false,
            'columns' => ['name', 'membership_type', 'email', 'phone', 'is_active'],
            'filters' => [],
            'sort_column' => 'name',
            'sort_direction' => 'asc',
            'per_page' => 20,
            'config' => [],
        ];
    }

    /**
     * Indicate the view is a system default.
     */
    public function system(): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility' => 'system',
            'user_id' => null,
            'is_default' => true,
        ]);
    }

    /**
     * Indicate the view is shared via roles.
     */
    public function shared(): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility' => 'shared',
            'user_id' => null,
        ]);
    }
}
