<?php

declare(strict_types=1);

namespace App\Domain\Tax\Database\Factories;

use App\Domain\Tax\Models\OrganisationTaxClass;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganisationTaxClass>
 */
final class OrganisationTaxClassFactory extends Factory
{
    protected $model = OrganisationTaxClass::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->sentence(),
            'is_default' => false,
        ];
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }
}
