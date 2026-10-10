<?php

namespace Database\Factories;

use App\Enums\InspectionStatus;
use App\Models\Asset;
use App\Models\AssetInspection;
use App\Models\InspectionPolicy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssetInspection>
 */
class AssetInspectionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'inspection_policy_id' => InspectionPolicy::factory(),
            'asset_id' => Asset::factory(),
            'due_at' => now()->addYear()->toDateString(),
            'status' => InspectionStatus::Pending,
            'result' => null,
        ];
    }
}
