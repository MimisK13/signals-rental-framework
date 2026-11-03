<?php

declare(strict_types=1);

namespace App\Domain\Assets\Database\Factories;

use App\Domain\Assets\Models\Asset;
use App\Domain\Assets\Models\AssetUsage;
use App\Domain\Projects\Opportunities\Models\OpportunityItem;
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
