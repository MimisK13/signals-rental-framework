<?php

namespace App\Services\Availability;

use App\Contracts\DemandResolverContract;
use App\Enums\AssetAssignmentStatus;
use App\Enums\DemandPhase;
use App\Models\Demand;
use App\Models\OpportunityItem;
use App\Models\OpportunityItemAsset;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Blocks a returned serialised Asset until its return inspection is finalised.
 *
 * This is deliberately separate from the rental opportunity-item demand. The
 * rental claim closes when the Asset returns; this demand represents the
 * independent warehouse inspection hold that follows physical return.
 */
class InspectionHoldDemandResolver implements DemandResolverContract
{
    public function sourceType(): string
    {
        return 'inspection_hold';
    }

    public function syncDemands(Model $source): void
    {
        $asset = $this->asAsset($source);
        $item = $asset->relationLoaded('item')
            ? $asset->item
            : $asset->item()->with('opportunity')->first();

        if ($item === null) {
            $this->releaseDemands($asset);

            return;
        }

        $asset->loadMissing(['stockLevel.product', 'item.opportunity']);
        $stockLevel = $asset->stockLevel;
        $product = $stockLevel?->product;

        if (! $this->requiresHold($asset) || $stockLevel === null || $product === null || ! $stockLevel->isSerialised()) {
            $this->releaseDemands($asset);

            return;
        }

        $storeId = $item->return_store_id
            ?? $item->dispatch_store_id
            ?? $item->opportunity?->store_id;

        if ($storeId === null || $asset->returned_at === null) {
            $this->releaseDemands($asset);

            return;
        }

        $returnedAt = Carbon::parse($asset->returned_at);
        $endsAt = Demand::sentinel();
        $attributes = [
            'product_id' => $product->id,
            'store_id' => $storeId,
            'asset_id' => $stockLevel->id,
            'quantity' => 1,
            'starts_at' => $returnedAt,
            'ends_at' => $endsAt,
            'buffered_starts_at' => $returnedAt,
            'buffered_ends_at' => $endsAt,
            'source_type' => $this->sourceType(),
            'source_id' => $asset->id,
            'phase' => DemandPhase::Committed->value,
            'is_active' => true,
            'priority' => 0,
            'metadata' => [
                'reason' => 'awaiting_return_inspection',
                'assignment_id' => $asset->id,
                'opportunity_item_id' => $item->id,
                'opportunity_id' => $item->opportunity_id,
                'returned_at' => $returnedAt->toIso8601String(),
            ],
        ];

        $demand = Demand::query()
            ->where('source_type', $this->sourceType())
            ->where('source_id', $asset->id)
            ->orderBy('id')
            ->first() ?? new Demand;

        $demand->fill($attributes);

        if ($demand->getConnection()->getDriverName() === 'pgsql') {
            $demand->setRawAttributes(
                array_merge($demand->getAttributes(), [
                    'period' => Demand::periodExpression($returnedAt, $endsAt),
                ]),
                true,
            );
            $demand->fill($attributes);
        }

        $demand->save();
    }

    public function syncDemandsForItem(OpportunityItem $item): void
    {
        Demand::query()
            ->where('source_type', $this->sourceType())
            ->where('metadata->opportunity_item_id', $item->id)
            ->update([
                'phase' => DemandPhase::Void->value,
                'is_active' => false,
            ]);

        $item->loadMissing(['assets.stockLevel.product', 'assets.item.opportunity', 'opportunity']);

        foreach ($item->assets as $asset) {
            $this->syncDemands($asset);
        }
    }

    public function releaseDemands(Model $source): void
    {
        $asset = $this->asAsset($source);

        Demand::query()
            ->where('source_type', $this->sourceType())
            ->where('source_id', $asset->id)
            ->update([
                'phase' => DemandPhase::Void->value,
                'is_active' => false,
            ]);
    }

    public function releaseDemandsForItem(OpportunityItem $item): void
    {
        Demand::query()
            ->where('source_type', $this->sourceType())
            ->where('metadata->opportunity_item_id', $item->id)
            ->update([
                'phase' => DemandPhase::Void->value,
                'is_active' => false,
            ]);
    }

    public function resolvePhase(Model $source): DemandPhase
    {
        $this->asAsset($source);

        return DemandPhase::Committed;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildMetadata(Model $source): array
    {
        $asset = $this->asAsset($source);

        return [
            'reason' => 'awaiting_return_inspection',
            'assignment_id' => $asset->id,
        ];
    }

    private function requiresHold(OpportunityItemAsset $asset): bool
    {
        return $asset->status === AssetAssignmentStatus::CheckedIn
            && $asset->returned_at !== null
            && $asset->checked_at === null;
    }

    private function asAsset(Model $source): OpportunityItemAsset
    {
        if (! $source instanceof OpportunityItemAsset) {
            throw new InvalidArgumentException('InspectionHoldDemandResolver expects an OpportunityItemAsset.');
        }

        return $source;
    }
}
