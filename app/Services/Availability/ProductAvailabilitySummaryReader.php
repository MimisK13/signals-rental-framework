<?php

namespace App\Services\Availability;

use App\Data\Availability\GanttDemandBarData;
use App\Data\Availability\ProductAvailabilityDemandEntry;
use App\Data\Availability\ProductAvailabilitySummary;
use App\Models\Product;
use App\Services\AvailabilityService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Composes the canonical availability read models for Product Detail.
 *
 * This class deliberately does not calculate demand or stock. AvailabilityService
 * remains the sole owner of those semantics; this reader only selects the
 * period summary and prepares source-grouped presentation data.
 */
class ProductAvailabilitySummaryReader
{
    public function __construct(private readonly AvailabilityService $availability) {}

    public function read(Product $product, int $storeId, Carbon $from, Carbon $until): ProductAvailabilitySummary
    {
        $range = $this->availability->getAvailabilityRange($product->id, $storeId, $from, $until);
        $gantt = $this->availability->getGantt($product->id, $storeId, $from, $until);

        $peakDemand = $range->slots === []
            ? null
            : max(array_map(static fn ($slot): int => $slot->total_demanded, $range->slots));

        $peakShortage = $range->min_available === null
            ? null
            : max(0, -$range->min_available);

        return new ProductAvailabilitySummary(
            product_id: $product->id,
            store_id: $storeId,
            from: $range->from,
            until: $range->to,
            physical_stock: $gantt->total_stock,
            peak_demand: $peakDemand,
            minimum_availability: $range->min_available,
            peak_shortage: $peakShortage,
            has_shortage: $range->min_available === null ? null : $range->min_available < 0,
            calculated_at: $range->calculated_at,
            slots: $range->slots,
            demand_entries: $this->demandEntries($gantt->demands),
        );
    }

    /**
     * @param  list<GanttDemandBarData>  $demands
     * @return list<ProductAvailabilityDemandEntry>
     */
    private function demandEntries(array $demands): array
    {
        /** @var Collection<string, Collection<int, GanttDemandBarData>> $grouped */
        $grouped = collect($demands)->groupBy(
            static fn ($demand): string => $demand->source_type.':'.$demand->source_id,
        );

        return $grouped
            ->map(function (Collection $sourceDemands): ProductAvailabilityDemandEntry {
                $first = $sourceDemands->first();
                $phases = $sourceDemands
                    ->pluck('phase')
                    ->unique()
                    ->values()
                    ->implode(', ');

                return new ProductAvailabilityDemandEntry(
                    source_type: $first->source_type,
                    source_id: $first->source_id,
                    label: $first->source_name ?? $first->source_type.' #'.$first->source_id,
                    quantity: $sourceDemands->sum('quantity'),
                    from: $sourceDemands->min('period_start'),
                    until: $sourceDemands->max('period_end'),
                    phase: $phases,
                );
            })
            ->sortBy('from')
            ->values()
            ->all();
    }
}
