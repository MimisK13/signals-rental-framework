<?php

namespace Database\Factories;

use App\Models\DiscountCategory;
use App\Models\DiscountRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiscountRule>
 */
class DiscountRuleFactory extends Factory
{
    protected $model = DiscountRule::class;

    public function definition(): array
    {
        return [
            'discount_category_id' => DiscountCategory::factory(),
            'discount_percent' => fake()->randomElement(['5.00', '10.00', '15.00', '20.00']),
            'criteria' => [],
            'valid_from' => null,
            'valid_to' => null,
            'sort_order' => 0,
            'rule_scope' => 'per_line',
        ];
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

    public function blanket(): static
    {
        return $this->state(fn (): array => [
            'criteria' => [],
            'discount_percent' => '10.00',
        ]);
    }
}
