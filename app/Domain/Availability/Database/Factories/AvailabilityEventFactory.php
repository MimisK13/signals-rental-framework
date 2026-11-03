<?php

declare(strict_types=1);

namespace App\Domain\Availability\Database\Factories;

use App\Domain\Availability\Enums\AvailabilityEventType;
use App\Domain\Availability\Models\AvailabilityEvent;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AvailabilityEvent>
 */
class AvailabilityEventFactory extends Factory
{
    protected $model = AvailabilityEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_type' => AvailabilityEventType::AvailabilityRecalculated,
            'product_id' => Product::factory(),
            'store_id' => Store::factory(),
            'demand_id' => null,
            'source_type' => null,
            'source_id' => null,
            'payload' => [],
        ];
    }

    /**
     * An event of the given type.
     */
    public function ofType(AvailabilityEventType $type): static
    {
        return $this->state(fn (): array => ['event_type' => $type]);
    }
}
