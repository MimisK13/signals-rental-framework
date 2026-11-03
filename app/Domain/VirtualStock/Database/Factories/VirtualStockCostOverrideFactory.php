<?php

declare(strict_types=1);

namespace App\Domain\VirtualStock\Database\Factories;

use App\Domain\Projects\Opportunities\Models\Opportunity;
use App\Domain\VirtualStock\Models\VirtualStockCostOverride;
use App\Domain\VirtualStock\Models\VirtualStockIntake;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VirtualStockCostOverride>
 */
class VirtualStockCostOverrideFactory extends Factory
{
    protected $model = VirtualStockCostOverride::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'virtual_stock_intake_id' => VirtualStockIntake::factory(),
            'opportunity_id' => Opportunity::factory(),
            'unit_cost_override' => fake()->numberBetween(1000, 5000),
        ];
    }
}
