<?php

declare(strict_types=1);

namespace App\Domain\Availability\Data;

use App\Data\Concerns\FormatsTimestamps;
use App\Domain\Availability\Models\AvailabilityDailySummary;
use App\Domain\Availability\Services\AvailabilityService;
use App\Models\Product;
use Spatie\LaravelData\Data;

/**
 * One store-wide shortage entry: a product/store/day whose availability dipped
 * below zero, derived from the {@see AvailabilityDailySummary} read model.
 *
 * `available` is the day's worst (minimum) availability — a negative number —
 * and `severity` is its magnitude (how many units short at the worst point).
 * This is the panel/widget-level shortage sweep
 * ({@see AvailabilityService::getShortages()}); the granular,
 * per-line contributing-demand detail is served by the opportunity-scoped
 * shortage endpoints.
 */
class StoreShortageData extends Data
{
    use FormatsTimestamps;

    public function __construct(
        public int $product_id,
        public ?string $product_name,
        public int $store_id,
        public string $date,
        public int $available,
        public int $severity,
        public ?string $calculated_at,
    ) {}

    public static function fromModel(AvailabilityDailySummary $summary): self
    {
        return new self(
            product_id: $summary->product_id,
            product_name: $summary->relationLoaded('product') ? $summary->product?->name : null,
            store_id: $summary->store_id,
            date: $summary->date->toDateString(),
            available: $summary->min_available,
            // Severity is the depth of the worst shortage on the day (a positive
            // count of units short), so consumers can sort/threshold on it.
            severity: max(0, -$summary->min_available),
            calculated_at: self::formatTimestamp($summary->calculated_at),
        );
    }
}
