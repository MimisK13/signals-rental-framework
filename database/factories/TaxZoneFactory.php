<?php

namespace Database\Factories;

use App\Models\TaxZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaxZone>
 */
class TaxZoneFactory extends Factory
{
    protected $model = TaxZone::class;

    public function definition(): array
    {
        $slug = str_replace('-', '_', fake()->unique()->slug(2));

        return [
            'name' => fake()->words(2, true),
            'slug' => $slug,
            'description' => fake()->optional()->sentence(),
            'priority' => 0,
            'is_active' => true,
        ];
    }

    public function uk(): static
    {
        return $this->state(fn (): array => [
            'name' => 'United Kingdom',
            'slug' => 'uk',
            'priority' => 100,
        ]);
    }

    public function euMember(): static
    {
        return $this->state(fn (): array => [
            'name' => 'EU Member States',
            'slug' => 'eu_member',
            'priority' => 80,
        ]);
    }

    public function euOther(): static
    {
        return $this->state(fn (): array => [
            'name' => 'EU Other',
            'slug' => 'eu_other',
            'priority' => 60,
        ]);
    }

    public function world(): static
    {
        return $this->state(fn (): array => [
            'name' => 'Rest of World',
            'slug' => 'world',
            'priority' => 10,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }
}
