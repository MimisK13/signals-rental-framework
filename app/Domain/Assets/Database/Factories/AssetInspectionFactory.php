<?php

declare(strict_types=1);

namespace App\Domain\Assets\Database\Factories;

use App\Domain\Assets\Enums\InspectionStatus;
use App\Domain\Assets\Models\Asset;
use App\Domain\Assets\Models\AssetInspection;
use App\Domain\Assets\Models\InspectionPolicy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssetInspection>
 */
class AssetInspectionFactory extends Factory
{
    protected $model = AssetInspection::class;

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
