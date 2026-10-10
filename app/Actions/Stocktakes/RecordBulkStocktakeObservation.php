<?php

namespace App\Actions\Stocktakes;

use App\Data\Stocktakes\RecordBulkStocktakeObservationData;
use App\Enums\StockMethod;
use App\Enums\StocktakeObservationType;
use App\Models\StockLevel;
use App\Models\Stocktake;
use App\Models\StocktakeLine;
use App\Models\StocktakeObservation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RecordBulkStocktakeObservation
{
    public function __invoke(StocktakeLine $line, RecordBulkStocktakeObservationData $data): StocktakeObservation
    {
        Gate::authorize('stock.count');

        $this->validateQuantity($data->counted_quantity);

        /** @var User $user */
        $user = auth()->user();

        return DB::transaction(function () use ($line, $data, $user): StocktakeObservation {
            $lockedLine = StocktakeLine::query()
                ->with('stockLevel.product')
                ->lockForUpdate()
                ->findOrFail($line->id);
            $stocktake = Stocktake::query()->lockForUpdate()->findOrFail($lockedLine->stocktake_id);

            Gate::authorize('access', new StockLevel(['store_id' => $stocktake->store_id]));

            if (! $stocktake->isInProgress()) {
                throw ValidationException::withMessages([
                    'stocktake' => 'Observations can only be recorded while a Stocktake is in progress.',
                ]);
            }

            if (
                $lockedLine->stockLevel === null
                || $lockedLine->stockLevel->asset_id !== null
                || $lockedLine->stockLevel->product?->stock_method === StockMethod::Serialised
                || $lockedLine->stockLevel->isVirtualAsset()
                || $lockedLine->stockLevel->container_stock_level_id !== null
            ) {
                throw ValidationException::withMessages([
                    'stocktake_line' => 'Only physical bulk StockLevels can be counted in this Stocktake.',
                ]);
            }

            $observation = StocktakeObservation::create([
                'stocktake_id' => $stocktake->id,
                'stocktake_line_id' => $lockedLine->id,
                'observation_type' => StocktakeObservationType::BulkCount,
                'counted_quantity' => $data->counted_quantity,
                'counted_by_user_id' => $user->id,
                'counted_at' => now('UTC'),
                'notes' => $data->notes !== null ? trim($data->notes) : null,
            ]);

            $lockedLine->forceFill(['accepted_observation_id' => $observation->id])->save();

            return $observation->fresh(['countedBy']);
        });
    }

    private function validateQuantity(string $quantity): void
    {
        $normalized = trim($quantity);

        if ($normalized === '' || ! preg_match('/^(?:\d+|\d+\.\d{1,2})$/', $normalized)) {
            throw ValidationException::withMessages([
                'counted_quantity' => 'Counted quantity must be a non-negative number with up to two decimal places.',
            ]);
        }
    }
}
