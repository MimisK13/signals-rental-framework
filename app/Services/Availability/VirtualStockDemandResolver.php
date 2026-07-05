<?php

namespace App\Services\Availability;

use App\Contracts\DemandResolverContract;
use App\Enums\DemandPhase;
use App\Enums\StockMethod;
use App\Models\Demand;
use App\Models\Product;
use App\Models\VirtualStockIntake;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Translates virtual stock intakes into negative availability demand rows.
 *
 * On confirm, one bulk demand row is written with quantity = -intake.quantity
 * over the half-open window [available_from, available_until). On return or
 * cancel the row is voided (inactive). Demand writes are invoked from Verbs
 * event handle() projections so they rebuild identically on replay.
 *
 * Allocation events are deferred to P43 — this slice only models supply windows.
 */
class VirtualStockDemandResolver implements DemandResolverContract
{
    public function sourceType(): string
    {
        return 'virtual_stock_intake';
    }

    public function resolvePhase(Model $source): DemandPhase
    {
        $intake = $this->asIntake($source);

        return match ($intake->status) {
            'confirmed', 'received' => DemandPhase::Committed,
            default => DemandPhase::Void,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function buildMetadata(Model $source): array
    {
        $intake = $this->asIntake($source);

        return [
            'virtual_stock_intake_id' => $intake->id,
            'virtual_stock_intake_number' => $intake->number,
            'purchase_order_id' => $intake->purchase_order_id,
            'purchase_order_item_id' => $intake->purchase_order_item_id,
        ];
    }

    /**
     * Create or update the supply demand row for a confirmed/received intake.
     */
    public function syncDemands(Model $source): void
    {
        $intake = $this->asIntake($source);

        if ($intake->demand_id === null || $this->usesSerialisedAssetSupply($intake)) {
            return;
        }

        $this->syncSupplyDemand($intake, (int) $intake->demand_id);
    }

    /**
     * Idempotent supply demand upsert keyed on the fire-time demand PK.
     */
    public function syncSupplyDemand(VirtualStockIntake $intake, int $demandId): void
    {
        if ($this->usesSerialisedAssetSupply($intake)) {
            if ($demandId > 0) {
                Demand::query()->whereKey($demandId)->update([
                    'phase' => DemandPhase::Void->value,
                    'is_active' => false,
                ]);
            }

            return;
        }

        if (! in_array($intake->status, ['confirmed', 'received'], true)) {
            $this->releaseDemands($intake);

            return;
        }

        $phase = $this->resolvePhase($intake);

        if (! $phase->isActive()) {
            $this->releaseDemands($intake);

            return;
        }

        $startsAt = Carbon::parse($intake->available_from);
        $endsAt = Carbon::parse($intake->available_until);
        [$bufferedStart, $bufferedEnd] = Demand::bufferedPeriod($startsAt, $endsAt, 0, 0);

        $attributes = [
            'product_id' => $intake->product_id,
            'store_id' => $intake->store_id,
            'asset_id' => null,
            'quantity' => -abs($intake->quantity),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'buffered_starts_at' => $bufferedStart,
            'buffered_ends_at' => $bufferedEnd,
            'source_type' => $this->sourceType(),
            'source_id' => $intake->id,
            'phase' => $phase->value,
            'is_active' => $phase->isActive(),
            'priority' => 0,
            'metadata' => $this->buildMetadata($intake),
        ];

        $demand = Demand::query()->find($demandId);

        if ($demand === null) {
            $demand = new Demand($attributes);
            $demand->id = $demandId;
        } else {
            $demand->fill($attributes);
        }

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

    /**
     * Void all supply demands for the intake.
     */
    public function releaseDemands(Model $source): void
    {
        $intake = $this->asIntake($source);

        Demand::query()
            ->where('source_type', $this->sourceType())
            ->where('source_id', $intake->id)
            ->update([
                'phase' => DemandPhase::Void->value,
                'is_active' => false,
            ]);
    }

    protected function asIntake(Model $source): VirtualStockIntake
    {
        if (! $source instanceof VirtualStockIntake) {
            throw new InvalidArgumentException(
                'VirtualStockDemandResolver expects a VirtualStockIntake, got '.$source::class,
            );
        }

        return $source;
    }

    protected function usesSerialisedAssetSupply(VirtualStockIntake $intake): bool
    {
        $product = Product::query()->find($intake->product_id);

        return $product !== null && $product->stock_method === StockMethod::Serialised;
    }
}
