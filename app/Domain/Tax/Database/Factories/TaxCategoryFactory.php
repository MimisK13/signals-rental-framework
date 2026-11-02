<?php

declare(strict_types=1);

namespace App\Domain\Tax\Database\Factories;

use App\Domain\Tax\Enums\TaxCategoryType;
use App\Domain\Tax\Models\TaxCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaxCategory>
 */
final class TaxCategoryFactory extends Factory
{
    protected $model = TaxCategory::class;

    public function definition(): array
    {
        $type = fake()->randomElement(TaxCategoryType::cases());
        $slug = $type->value.'_'.fake()->unique()->numerify('###');

        return [
            'name' => $type->label(),
            'slug' => $slug,
            'type' => $type,
            'description' => fake()->optional()->sentence(),
            'is_default' => false,
            'is_active' => true,
        ];
    }

    public function standard(): static
    {
        return $this->state(fn (): array => [
            'name' => 'Standard',
            'slug' => 'standard',
            'type' => TaxCategoryType::Standard,
            'is_default' => true,
        ]);
    }

    public function reduced(): static
    {
        return $this->state(fn (): array => [
            'name' => 'Reduced',
            'slug' => 'reduced',
            'type' => TaxCategoryType::Reduced,
        ]);
    }

    public function zero(): static
    {
        return $this->state(fn (): array => [
            'name' => 'Zero Rated',
            'slug' => 'zero',
            'type' => TaxCategoryType::Zero,
        ]);
    }

    public function exempt(): static
    {
        return $this->state(fn (): array => [
            'name' => 'Exempt',
            'slug' => 'exempt',
            'type' => TaxCategoryType::Exempt,
        ]);
    }
}
