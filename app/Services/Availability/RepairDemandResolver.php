<?php

namespace App\Services\Availability;

use App\Contracts\DemandResolverContract;
use App\Enums\DemandPhase;
use App\Models\Demand;
use App\Models\Repair;
use App\Models\StockLevel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Projects unusable repairs into the canonical availability Demand stream.
 */
class RepairDemandResolver implements DemandResolverContract
{
    public function sourceType(): string
    {
        return 'repair';
    }

    public function resolvePhase(Model $source): DemandPhase
    {
        return $this->asRepair($source)->createsAvailabilityDemand()
            ? DemandPhase::Committed
            : DemandPhase::Void;
    }

    /** @return array<string, mixed> */
    public function buildMetadata(Model $source): array
    {
        $repair = $this->asRepair($source);

        return [
            'repair_id' => $repair->id,
            'repair_status' => $repair->statusEnum()->value,
            'is_usable' => $repair->is_usable,
            'scope' => $repair->asset_id === null ? 'bulk' : 'serialized',
        ];
    }

    public function syncDemands(Model $source): void
    {
        $repair = $this->asRepair($source);
        $existing = $this->demand($repair);

        if ($repair->asset_id !== null) {
            $asset = $repair->asset ?: $repair->asset()->first();

            if ($asset !== null && ! $asset->isOperationallyActive()) {
                $this->releaseDemand($existing);

                return;
            }
        }

        if (! $repair->createsAvailabilityDemand()) {
            $this->releaseDemand($existing);

            return;
        }

        $stockLevel = $repair->asset_id === null
            ? null
            : StockLevel::query()->where('asset_id', $repair->asset_id)->first();

        if ($repair->asset_id !== null && ($stockLevel === null || $stockLevel->isVirtualAsset() || ! $stockLevel->isSerialised())) {
            $this->releaseDemand($existing);

            return;
        }

        $startsAt = $repair->availabilityStartsAt();
        $endsAt = $repair->availabilityEndsAt();

        if ($stockLevel !== null && $this->hasOverlappingRepairDemand($repair, $stockLevel, $startsAt, $endsAt)) {
            $this->releaseDemand($existing);

            return;
        }

        $assetDemandId = $this->assetDemandId($repair, $stockLevel, $startsAt, $endsAt);
        $productId = $stockLevel !== null ? $stockLevel->product_id : $repair->product_id;
        $storeId = $stockLevel !== null ? $stockLevel->store_id : $repair->store_id;
        $quantity = $repair->asset_id === null || $assetDemandId === null ? $repair->quantity : 1;
        [$bufferedStart, $bufferedEnd] = Demand::bufferedPeriod($startsAt, $endsAt, 0, 0);
        $phase = $this->resolvePhase($repair);

        $attributes = [
            'product_id' => $productId,
            'store_id' => $storeId,
            'asset_id' => $assetDemandId,
            'quantity' => $quantity,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'buffered_starts_at' => $bufferedStart,
            'buffered_ends_at' => $bufferedEnd,
            'source_type' => $this->sourceType(),
            'source_id' => $repair->id,
            'phase' => $phase->value,
            'is_active' => $phase->isActive(),
            'priority' => 0,
            'metadata' => [
                ...$this->buildMetadata($repair),
                'asset_stock_level_id' => $stockLevel?->id,
                'asset_projection' => $assetDemandId === null && $stockLevel !== null ? 'product_quantity' : 'asset',
            ],
        ];

        $demand = $existing ?? new Demand;
        $demand->fill($attributes);

        if ($demand->getConnection()->getDriverName() === 'pgsql') {
            $demand->setRawAttributes(
                array_merge($demand->getAttributes(), [
                    'period' => Demand::periodExpression($bufferedStart, $bufferedEnd),
                ]),
                true,
            );
            $demand->fill($attributes);
        }

        $demand->save();
    }

    public function releaseDemands(Model $source): void
    {
        $repair = $this->asRepair($source);

        Demand::query()
            ->where('source_type', $this->sourceType())
            ->where('source_id', $repair->id)
            ->update([
                'phase' => DemandPhase::Void->value,
                'is_active' => false,
            ]);
    }

    public function syncForAsset(int $assetId): void
    {
        Repair::query()
            ->where('asset_id', $assetId)
            ->get()
            ->each(function (Repair $repair): void {
                $this->syncDemands($repair);
            });
    }

    protected function demand(Repair $repair): ?Demand
    {
        return Demand::query()
            ->where('source_type', $this->sourceType())
            ->where('source_id', $repair->id)
            ->first();
    }

    protected function assetDemandId(Repair $repair, ?StockLevel $stockLevel, Carbon $startsAt, Carbon $endsAt): ?int
    {
        if ($repair->asset_id === null || $stockLevel === null) {
            return null;
        }

        $hasOverlappingAssetDemand = Demand::query()
            ->active()
            ->where('asset_id', $stockLevel->id)
            ->where(function (Builder $query) use ($repair): void {
                $query->where('source_type', '!=', $this->sourceType())
                    ->orWhere('source_id', '!=', $repair->id);
            })
            ->overlapping($startsAt, $endsAt)
            ->exists();

        return $hasOverlappingAssetDemand ? null : $stockLevel->id;
    }

    protected function hasOverlappingRepairDemand(Repair $repair, StockLevel $stockLevel, Carbon $startsAt, Carbon $endsAt): bool
    {
        return Demand::query()
            ->active()
            ->where('source_type', $this->sourceType())
            ->where('source_id', '!=', $repair->id)
            ->where(function (Builder $query) use ($stockLevel): void {
                $query
                    ->where('asset_id', $stockLevel->id)
                    ->orWhere(function (Builder $projected) use ($stockLevel): void {
                        $projected
                            ->whereNull('asset_id')
                            ->whereJsonContains('metadata->asset_stock_level_id', $stockLevel->id);
                    });
            })
            ->overlapping($startsAt, $endsAt)
            ->exists();
    }

    protected function releaseDemand(?Demand $demand): void
    {
        if ($demand === null) {
            return;
        }

        $demand->forceFill([
            'phase' => DemandPhase::Void,
            'is_active' => false,
        ])->save();
    }

    protected function asRepair(Model $source): Repair
    {
        if (! $source instanceof Repair) {
            throw new InvalidArgumentException(
                'RepairDemandResolver expects a Repair, got '.$source::class,
            );
        }

        return $source;
    }
}
