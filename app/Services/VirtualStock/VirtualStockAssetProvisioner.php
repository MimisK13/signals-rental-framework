<?php

namespace App\Services\VirtualStock;

use App\Enums\AllowedStockType;
use App\Enums\DemandPhase;
use App\Enums\StockCategory;
use App\Enums\StockMethod;
use App\Models\Demand;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\VirtualStockIntake;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Projects serialised virtual stock_levels rows and per-asset supply demand rows.
 */
class VirtualStockAssetProvisioner
{
    public function __construct(
        private readonly VirtualAssetStatusRegistry $assetStatuses,
    ) {}

    /**
     * @param  list<array{stock_level_id: int, supply_demand_id: int, sequence: int, asset_number: string}>  $assets
     */
    public function provision(VirtualStockIntake $intake, array $assets): void
    {
        if ($assets === []) {
            return;
        }

        $product = Product::query()->find($intake->product_id);

        if ($product === null || $product->stock_method !== StockMethod::Serialised) {
            return;
        }

        $status = $this->assetStatuses->defaultStatus();
        $this->assetStatuses->assertKnown($status);

        $startsAt = Carbon::parse($intake->available_from);
        $endsAt = Carbon::parse($intake->available_until);
        [$bufferedStart, $bufferedEnd] = Demand::bufferedPeriod($startsAt, $endsAt, 0, 0);

        foreach ($assets as $asset) {
            $attributes = [
                'product_id' => $intake->product_id,
                'store_id' => $intake->store_id,
                'item_name' => $product->name,
                'asset_number' => $asset['asset_number'],
                'serial_number' => $asset['asset_number'],
                'stock_type' => AllowedStockType::Rental,
                'stock_category' => StockCategory::SerialisedStock,
                'quantity_held' => 1,
                'quantity_allocated' => 0,
                'quantity_unavailable' => 0,
                'quantity_on_order' => 0,
                'is_virtual' => true,
                'virtual_stock_intake_id' => $intake->id,
                'virtual_asset_status' => $status,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
            ];

            $stockLevel = StockLevel::query()->find($asset['stock_level_id']);

            if ($stockLevel !== null) {
                $belongsToIntake = $stockLevel->is_virtual
                    && (int) $stockLevel->virtual_stock_intake_id === (int) $intake->id;

                if (! $belongsToIntake) {
                    throw new InvalidArgumentException(
                        "Replay-stable stock level id {$asset['stock_level_id']} already belongs to a non-virtual asset.",
                    );
                }

                $stockLevel->fill($attributes);
                $stockLevel->save();
            } else {
                $stockLevel = new StockLevel($attributes);
                $stockLevel->id = $asset['stock_level_id'];
                $stockLevel->save();
            }

            $this->syncSupplyDemand(
                $intake,
                $asset['stock_level_id'],
                $asset['supply_demand_id'],
                $startsAt,
                $endsAt,
                $bufferedStart,
                $bufferedEnd,
            );
        }

        $this->syncPostgresPrimaryKeySequences();
    }

    private function syncPostgresPrimaryKeySequences(): void
    {
        $connection = (new StockLevel)->getConnection();

        if ($connection->getDriverName() !== 'pgsql') {
            return;
        }

        $connection->statement(
            "SELECT setval(pg_get_serial_sequence('stock_levels', 'id'), (SELECT COALESCE(MAX(id), 1) FROM stock_levels))",
        );
        $connection->statement(
            "SELECT setval(pg_get_serial_sequence('demands', 'id'), (SELECT COALESCE(MAX(id), 1) FROM demands))",
        );
    }

    public function syncSupplyDemand(
        VirtualStockIntake $intake,
        int $stockLevelId,
        int $demandId,
        ?Carbon $startsAt = null,
        ?Carbon $endsAt = null,
        ?Carbon $bufferedStart = null,
        ?Carbon $bufferedEnd = null,
    ): void {
        if (! in_array($intake->status, ['confirmed', 'received'], true)) {
            return;
        }

        $startsAt ??= Carbon::parse($intake->available_from);
        $endsAt ??= Carbon::parse($intake->available_until);
        [$bufferedStart, $bufferedEnd] = $bufferedStart !== null && $bufferedEnd !== null
            ? [$bufferedStart, $bufferedEnd]
            : Demand::bufferedPeriod($startsAt, $endsAt, 0, 0);

        $attributes = [
            'product_id' => $intake->product_id,
            'store_id' => $intake->store_id,
            'asset_id' => $stockLevelId,
            'quantity' => -1,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'buffered_starts_at' => $bufferedStart,
            'buffered_ends_at' => $bufferedEnd,
            'source_type' => 'virtual_stock_intake',
            'source_id' => $intake->id,
            'phase' => DemandPhase::Committed->value,
            'is_active' => true,
            'priority' => 0,
            'metadata' => [
                'virtual_stock_intake_id' => $intake->id,
                'virtual_stock_intake_number' => $intake->number,
                'virtual_stock_asset_id' => $stockLevelId,
                'supply_kind' => 'serialised_asset',
            ],
        ];

        $demand = Demand::query()->find($demandId);

        if ($demand !== null) {
            $belongsToIntake = $demand->source_type === 'virtual_stock_intake'
                && (int) $demand->source_id === (int) $intake->id
                && (int) ($demand->metadata['virtual_stock_asset_id'] ?? 0) === $stockLevelId;

            if (! $belongsToIntake) {
                throw new InvalidArgumentException(
                    "Replay-stable demand id {$demandId} already belongs to another source.",
                );
            }

            $demand->fill($attributes);
        } else {
            $demand = new Demand($attributes);
            $demand->id = $demandId;
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

    public static function assetNumberFor(VirtualStockIntake $intake, int $sequence): string
    {
        $padded = str_pad((string) $sequence, 2, '0', STR_PAD_LEFT);

        return $intake->number.'-'.$padded;
    }
}
