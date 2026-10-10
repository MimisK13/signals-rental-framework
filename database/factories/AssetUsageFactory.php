<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\AssetUsage;
use App\Models\OpportunityItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssetUsage>
 */
class AssetUsageFactory extends Factory
{
    protected $model = AssetUsage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'asset_id' => Asset::factory(),
            'opportunity_item_id' => OpportunityItem::factory(),
            'dispatched_at' => now()->subDay(),
            'returned_at' => null,
        ];
    }
}
