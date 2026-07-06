<?php

namespace Database\Factories;

use App\Models\Opportunity;
use App\Models\OpportunitySurchargeGroup;
use App\Models\SurchargeGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OpportunitySurchargeGroup>
 */
class OpportunitySurchargeGroupFactory extends Factory
{
    protected $model = OpportunitySurchargeGroup::class;

    public function definition(): array
    {
        return [
            'opportunity_id' => Opportunity::factory(),
            'surcharge_group_id' => SurchargeGroup::factory(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }
}
