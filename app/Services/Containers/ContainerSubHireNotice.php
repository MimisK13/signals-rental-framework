<?php

namespace App\Services\Containers;

use App\Models\StockLevel;
use App\Models\VirtualStockIntake;
use Illuminate\Support\Carbon;

/**
 * Informational sub-hire / virtual-stock context for a serialised item being
 * packed (serialised-containers.md §"Sub-hired items are allowed").
 */
class ContainerSubHireNotice
{
    /**
     * @return array{type: string, virtual_stock_intake_id: int, intake_number: string, supplier_name: string|null, return_by: string}|null
     */
    public function forStockLevel(StockLevel $stockLevel): ?array
    {
        $intake = VirtualStockIntake::query()
            ->with('purchaseOrder.supplier')
            ->where('product_id', $stockLevel->product_id)
            ->where('store_id', $stockLevel->store_id)
            ->whereIn('status', ['confirmed', 'received'])
            ->where('available_until', '>', Carbon::now('UTC'))
            ->orderByDesc('available_until')
            ->first();

        if ($intake === null) {
            return null;
        }

        return [
            'type' => 'sub_hire',
            'virtual_stock_intake_id' => $intake->id,
            'intake_number' => $intake->number,
            'supplier_name' => $intake->purchaseOrder?->supplier?->name,
            'return_by' => $intake->available_until->toIso8601String(),
        ];
    }
}
