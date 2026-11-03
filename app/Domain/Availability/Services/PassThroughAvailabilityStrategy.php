<?php

declare(strict_types=1);

namespace App\Domain\Availability\Services;

use App\Domain\Availability\Contracts\AvailabilityStrategyContract;
use App\Domain\Availability\Models\Demand;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The open-source default availability strategy: a pure pass-through.
 *
 * Both hooks return their input unchanged, so the pipeline behaves exactly as it
 * would with no strategy at all. A plugin rebinds
 * {@see AvailabilityStrategyContract} to inject buffer-stock, sub-hire
 * augmentation, or priority-displacement behaviour without the core changing.
 *
 * Tenant-ignorant and side-effect free.
 */
class PassThroughAvailabilityStrategy implements AvailabilityStrategyContract
{
    /**
     * @param  Collection<int, Demand>  $demands
     * @return Collection<int, Demand>
     */
    public function preCalculation(
        int $productId,
        int $storeId,
        Carbon $rangeStart,
        Carbon $rangeEnd,
        Collection $demands,
    ): Collection {
        return $demands;
    }

    /**
     * @param  Collection<int, array{slot_start: Carbon, total_stock: int, total_demanded: int, available: int, breakdown: array<string, int>}>  $slotResults
     * @return Collection<int, array{slot_start: Carbon, total_stock: int, total_demanded: int, available: int, breakdown: array<string, int>}>
     */
    public function postCalculation(
        int $productId,
        int $storeId,
        Carbon $rangeStart,
        Carbon $rangeEnd,
        Collection $slotResults,
    ): Collection {
        return $slotResults;
    }
}
