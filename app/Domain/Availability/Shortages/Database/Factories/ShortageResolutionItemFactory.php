<?php

declare(strict_types=1);

namespace App\Domain\Availability\Shortages\Database\Factories;

use App\Domain\Availability\Shortages\Models\ShortageResolution;
use App\Domain\Availability\Shortages\Models\ShortageResolutionItem;
use App\Models\OpportunityItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShortageResolutionItem>
 */
class ShortageResolutionItemFactory extends Factory
{
    protected $model = ShortageResolutionItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shortage_resolution_id' => ShortageResolution::factory(),
            'opportunity_item_id' => OpportunityItem::factory(),
            'quantity_allocated' => fake()->numberBetween(1, 5),
        ];
    }
}
