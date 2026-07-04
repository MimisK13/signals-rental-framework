<?php

namespace App\Services\Containers;

use App\Enums\AssetAssignmentStatus;
use App\Enums\ContainerAvailabilityMode;
use App\Models\Container;
use App\Models\Opportunity;
use App\Models\OpportunityItemAsset;
use App\Models\StockLevel;
use Illuminate\Support\Collection;

/**
 * Detects transport housings eligible for repack-on-return during warehouse check-in
 * (serialised-containers.md §"Repack on Return").
 */
class RepackOnReturnEligibility
{
    public function isEligibleHousing(StockLevel $housing): bool
    {
        if (! $housing->isSerialised()) {
            return false;
        }

        $product = $housing->relationLoaded('product')
            ? $housing->product
            : $housing->loadMissing('product')->product;

        if (! $product?->is_containerable) {
            return false;
        }

        if ($product->container_availability_mode !== ContainerAvailabilityMode::Transport) {
            return false;
        }

        if (! $product->container_repack_on_return) {
            return false;
        }

        return ! Container::query()
            ->active()
            ->where('serialised_item_id', $housing->id)
            ->exists();
    }

    /**
     * @return Collection<int, Opportunity>
     */
    public function checkedOutOpportunities(StockLevel $housing): Collection
    {
        $opportunityIds = OpportunityItemAsset::query()
            ->where('stock_level_id', $housing->id)
            ->whereIn('status', [
                AssetAssignmentStatus::Dispatched->value,
                AssetAssignmentStatus::OnHire->value,
            ])
            ->whereHas('item')
            ->with('item:id,opportunity_id')
            ->get()
            ->pluck('item.opportunity_id')
            ->filter()
            ->unique()
            ->values();

        if ($opportunityIds->isEmpty()) {
            return collect();
        }

        return Opportunity::query()
            ->whereIn('id', $opportunityIds)
            ->orderBy('id')
            ->get(['id', 'subject', 'reference']);
    }

    public function isCheckedOutOnOpportunity(StockLevel $housing, int $opportunityId): bool
    {
        return OpportunityItemAsset::query()
            ->where('stock_level_id', $housing->id)
            ->whereHas('item', fn ($query) => $query->where('opportunity_id', $opportunityId))
            ->whereIn('status', [
                AssetAssignmentStatus::Dispatched->value,
                AssetAssignmentStatus::OnHire->value,
            ])
            ->exists();
    }
}
