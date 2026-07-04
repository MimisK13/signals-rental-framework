<?php

namespace App\Services\Shortages;

use App\Models\ProductSupplierCost;
use Illuminate\Support\Carbon;

/**
 * Estimates sub-hire cost from a supplier cost row, quantity, and hire window.
 *
 * Cost basis handling (sub-hire windows use per_day or per_unit; flat is treated
 * as a one-off fee regardless of quantity):
 *  - per_unit: unit rate × quantity
 *  - per_day: daily rate × rental days × quantity
 *  - per_week: weekly rate × rental weeks × quantity
 *  - flat: flat fee (quantity-independent)
 */
class SubHireCostCalculator
{
    public function rentalDays(Carbon $startsAt, Carbon $endsAt): int
    {
        $start = $startsAt->copy()->utc()->startOfDay();
        $end = $endsAt->copy()->utc()->startOfDay();

        return max(1, (int) $start->diffInDays($end));
    }

    public function rentalWeeks(Carbon $startsAt, Carbon $endsAt): int
    {
        return max(1, (int) ceil($this->rentalDays($startsAt, $endsAt) / 7));
    }

    /**
     * Total estimated cost in minor units for the line.
     */
    public function estimatedTotal(
        ProductSupplierCost $supplierCost,
        int $quantity,
        Carbon $startsAt,
        Carbon $endsAt,
    ): int {
        $rate = (int) $supplierCost->cost;

        return match ($supplierCost->cost_basis) {
            'per_day' => $rate * $this->rentalDays($startsAt, $endsAt) * $quantity,
            'per_week' => $rate * $this->rentalWeeks($startsAt, $endsAt) * $quantity,
            'flat' => $rate,
            default => $rate * $quantity,
        };
    }

    /**
     * Effective PO line unit cost (minor units) so totals = qty × unit_cost.
     */
    public function purchaseOrderUnitCost(
        ProductSupplierCost $supplierCost,
        Carbon $startsAt,
        Carbon $endsAt,
    ): int {
        $rate = (int) $supplierCost->cost;

        return match ($supplierCost->cost_basis) {
            'per_day' => $rate * $this->rentalDays($startsAt, $endsAt),
            'per_week' => $rate * $this->rentalWeeks($startsAt, $endsAt),
            'flat' => $rate,
            default => $rate,
        };
    }

    /**
     * PO line quantity — flat basis uses qty 1 regardless of shortfall count.
     */
    public function purchaseOrderQuantity(
        ProductSupplierCost $supplierCost,
        int $shortfallQuantity,
    ): string {
        if ($supplierCost->cost_basis === 'flat') {
            return '1.00';
        }

        return bcadd((string) $shortfallQuantity, '0', 2);
    }
}
