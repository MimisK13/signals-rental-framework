<?php

declare(strict_types=1);

namespace App\Domain\Availability\Shortages\Database\Factories;

use App\Domain\Availability\Shortages\Enums\WaitlistMonitorStatus;
use App\Domain\Availability\Shortages\Models\ShortageResolution;
use App\Domain\Availability\Shortages\Models\ShortageWaitlistMonitor;
use App\Domain\Products\Models\Product;
use App\Domain\Stores\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShortageWaitlistMonitor>
 */
class ShortageWaitlistMonitorFactory extends Factory
{
    protected $model = ShortageWaitlistMonitor::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shortage_resolution_id' => ShortageResolution::factory()->monitoring(),
            'opportunity_item_id' => null,
            'product_id' => Product::factory(),
            'store_id' => Store::factory(),
            'quantity_needed' => fake()->numberBetween(1, 5),
            'starts_at' => now(),
            'ends_at' => now()->addDays(3),
            'status' => WaitlistMonitorStatus::Active->value,
            'expires_at' => now()->addDays(7),
        ];
    }

    public function matched(): static
    {
        return $this->state(fn (): array => [
            'status' => WaitlistMonitorStatus::Matched->value,
            'matched_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'status' => WaitlistMonitorStatus::Expired->value,
            'expires_at' => now()->subDay(),
        ]);
    }
}
