<?php

namespace App\Services\VirtualStock;

use App\Enums\AssetAssignmentStatus;
use App\Models\OpportunityItem;
use App\Models\OpportunityItemAsset;
use App\Models\ShortageResolution;
use App\Models\StockLevel;
use App\Models\VirtualStockIntake;
use Brick\Math\BigDecimal;

/**
 * Pre-flight checks before returning a virtual stock intake to the supplier.
 *
 * Returning an intake voids its supply demand and removes apportioned costs but
 * must never mutate opportunity allocation/demand rows — only warn when jobs
 * still have quantity out on hire.
 */
class VirtualStockIntakeReturnGuard
{
    /**
     * @return list<array{
     *     opportunity_id: int,
     *     opportunity_subject: string,
     *     opportunity_item_id: int,
     *     line_name: string,
     *     quantity_out: string,
     * }>
     */
    public function outstandingAllocations(VirtualStockIntake $intake): array
    {
        $outstanding = $this->outstandingFromShortageResolution($intake);
        $seenItemIds = array_column($outstanding, 'opportunity_item_id');

        foreach ($this->outstandingFromVirtualAssets($intake) as $row) {
            if (in_array($row['opportunity_item_id'], $seenItemIds, true)) {
                continue;
            }

            $outstanding[] = $row;
        }

        return $outstanding;
    }

    /**
     * @return list<array{
     *     opportunity_id: int,
     *     opportunity_subject: string,
     *     opportunity_item_id: int,
     *     line_name: string,
     *     quantity_out: string,
     * }>
     */
    private function outstandingFromShortageResolution(VirtualStockIntake $intake): array
    {
        $resolution = ShortageResolution::query()
            ->where('virtual_stock_intake_id', $intake->id)
            ->with(['items.opportunityItem.opportunity', 'items.opportunityItem.assets'])
            ->first();

        if ($resolution === null) {
            return [];
        }

        $outstanding = [];

        foreach ($resolution->items as $pivot) {
            $item = $pivot->opportunityItem;
            $quantityOut = $this->quantityStillOut($item);

            if (bccomp($quantityOut, '0', 2) <= 0) {
                continue;
            }

            $outstanding[] = [
                'opportunity_id' => (int) $item->opportunity_id,
                'opportunity_subject' => (string) ($item->opportunity !== null
                    ? $item->opportunity->subject
                    : 'Opportunity #'.$item->opportunity_id),
                'opportunity_item_id' => (int) $item->id,
                'line_name' => (string) $item->name,
                'quantity_out' => $quantityOut,
            ];
        }

        return $outstanding;
    }

    /**
     * @return list<array{
     *     opportunity_id: int,
     *     opportunity_subject: string,
     *     opportunity_item_id: int,
     *     line_name: string,
     *     quantity_out: string,
     * }>
     */
    private function outstandingFromVirtualAssets(VirtualStockIntake $intake): array
    {
        $assetIds = StockLevel::query()
            ->where('virtual_stock_intake_id', $intake->id)
            ->where('is_virtual', true)
            ->pluck('id');

        if ($assetIds->isEmpty()) {
            return [];
        }

        $assignments = OpportunityItemAsset::query()
            ->whereIn('stock_level_id', $assetIds)
            ->with('item.opportunity')
            ->get();

        $outstanding = [];

        foreach ($assignments as $assignment) {
            $item = $assignment->item;

            if ($item === null) {
                continue;
            }

            if ($assignment->dispatched_at === null
                || $assignment->status->value >= AssetAssignmentStatus::CheckedIn->value) {
                continue;
            }

            $outstanding[] = [
                'opportunity_id' => (int) $item->opportunity_id,
                'opportunity_subject' => (string) ($item->opportunity !== null
                    ? $item->opportunity->subject
                    : 'Opportunity #'.$item->opportunity_id),
                'opportunity_item_id' => (int) $item->id,
                'line_name' => (string) $item->name,
                'quantity_out' => '1',
            ];
        }

        return $outstanding;
    }

    public function hasOutstandingAllocations(VirtualStockIntake $intake): bool
    {
        return $this->outstandingAllocations($intake) !== [];
    }

    private function quantityStillOut(OpportunityItem $item): string
    {
        if ($item->assets->isNotEmpty()) {
            $out = $item->assets
                ->filter(static fn ($asset): bool => $asset->dispatched_at !== null
                    && $asset->status->value < AssetAssignmentStatus::CheckedIn->value)
                ->count();

            return (string) $out;
        }

        return BigDecimal::of($item->dispatched_quantity)
            ->minus(BigDecimal::of($item->returned_quantity))
            ->toScale(2)
            ->__toString();
    }
}
