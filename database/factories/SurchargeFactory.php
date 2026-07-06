<?php

namespace Database\Factories;

use App\Models\Surcharge;
use App\Models\SurchargeGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Surcharge>
 */
class SurchargeFactory extends Factory
{
    protected $model = Surcharge::class;

    public function definition(): array
    {
        return [
            'surcharge_group_id' => SurchargeGroup::factory(),
            'name' => fake()->words(3, true),
            'percent_value' => '5.00',
            'fixed_value_minor' => null,
            'criteria' => [],
            'is_default' => true,
            'product_tax_class_id' => null,
            'valid_from' => null,
            'valid_to' => null,
            'sort_order' => 0,
        ];
    }

    public function percent(string $percent = '5.00'): static
    {
        return $this->state(fn (): array => [
            'percent_value' => $percent,
            'fixed_value_minor' => null,
        ]);
    }

    public function fixed(int $minor = 1000): static
    {
        return $this->state(fn (): array => [
            'percent_value' => null,
            'fixed_value_minor' => $minor,
        ]);
    }

    public function nonDefault(): static
    {
        return $this->state(fn (): array => [
            'is_default' => false,
        ]);
    }

    /**
     * @param  array<string, mixed>  $criteria
     */
    public function withCriteria(array $criteria): static
    {
        return $this->state(fn (): array => [
            'criteria' => $criteria,
        ]);
    }
}
