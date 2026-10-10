<?php

namespace App\Actions\Stocktakes;

use App\Enums\StocktakeObservationType;
use App\Models\ContainerItem;
use App\Models\StockLevel;
use App\Models\Stocktake;
use App\Models\StocktakeLine;
use App\Models\StocktakeObservation;
use App\Models\User;
use App\Services\Stocktakes\SerializedStocktakeIdentifierResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RecordSerializedStocktakeObservation
{
    public function __invoke(Stocktake $stocktake, string $identifier, ?string $notes = null): StocktakeObservation
    {
        Gate::authorize('stock.count');

        $identifier = trim($identifier);

        if ($identifier === '') {
            throw ValidationException::withMessages([
                'identifier' => 'An Asset barcode, asset number, or serial number is required.',
            ]);
        }

        /** @var User $user */
        $user = auth()->user();

        return DB::transaction(function () use ($stocktake, $identifier, $notes, $user): StocktakeObservation {
            $lockedStocktake = Stocktake::query()->lockForUpdate()->findOrFail($stocktake->id);

            Gate::authorize('access', new StockLevel(['store_id' => $lockedStocktake->store_id]));

            if (! $lockedStocktake->isInProgress()) {
                throw ValidationException::withMessages([
                    'stocktake' => 'Observations can only be recorded while a Stocktake is in progress.',
                ]);
            }

            $matches = app(SerializedStocktakeIdentifierResolver::class)->resolve($identifier);

            if ($matches->count() > 1) {
                throw ValidationException::withMessages([
                    'identifier' => 'The identifier matches multiple serialized Assets. Use another identifier.',
                ]);
            }

            if ($matches->isEmpty()) {
                return StocktakeObservation::create([
                    'stocktake_id' => $lockedStocktake->id,
                    'observation_type' => StocktakeObservationType::UnresolvedItem,
                    'identifier' => $identifier,
                    'counted_by_user_id' => $user->id,
                    'counted_at' => now('UTC'),
                    'notes' => $this->nullableNotes($notes),
                ]);
            }

            $stockLevel = $matches->firstOrFail();
            $asset = $stockLevel->asset;

            if ($asset === null || ! $stockLevel->isSerialised()) {
                throw ValidationException::withMessages([
                    'identifier' => 'Only known physical serialized Assets can be observed.',
                ]);
            }

            $existing = StocktakeObservation::query()
                ->where('stocktake_id', $lockedStocktake->id)
                ->where('asset_id', $asset->id)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $existing->load(['asset', 'stockLevel', 'line']);
            }

            $line = StocktakeLine::query()
                ->where('stocktake_id', $lockedStocktake->id)
                ->where('asset_id', $asset->id)
                ->lockForUpdate()
                ->first();

            $containerItem = ContainerItem::query()
                ->with('container:id,uuid,name,barcode')
                ->where('serialised_item_id', $stockLevel->id)
                ->whereNull('unpacked_at')
                ->first();
            $container = $containerItem?->container;
            $asset->loadMissing(['repairs', 'inspections', 'usages']);
            $repair = $asset->repairs->sortByDesc('id')->first();
            $inspection = $asset->inspections->sortByDesc('id')->first();
            $usage = $asset->usages->whereNull('returned_at')->sortByDesc('id')->first();

            $observation = StocktakeObservation::create([
                'stocktake_id' => $lockedStocktake->id,
                'stocktake_line_id' => $line?->id,
                'asset_id' => $asset->id,
                'stock_level_id' => $stockLevel->id,
                'identifier' => $identifier,
                'observation_type' => StocktakeObservationType::SerializedScan,
                'counted_by_user_id' => $user->id,
                'counted_at' => now('UTC'),
                'notes' => $this->nullableNotes($notes),
                'asset_number_snapshot' => $stockLevel->asset_number,
                'serial_number_snapshot' => $stockLevel->serial_number,
                'barcode_snapshot' => $stockLevel->barcode,
                'lifecycle_status_snapshot' => $asset->lifecycleStatus()->value,
                'repair_status_snapshot' => $repair?->getRawOriginal('status'),
                'inspection_status_snapshot' => $inspection?->getRawOriginal('status'),
                'asset_usage_id_snapshot' => $usage?->id,
                'store_id_snapshot' => $stockLevel->store_id,
                'container_id_snapshot' => $container?->id,
                'container_name_snapshot' => $container?->name,
                'container_barcode_snapshot' => $container?->barcode,
            ]);

            if ($line !== null) {
                $line->forceFill(['accepted_observation_id' => $observation->id])->save();
            }

            return $observation->load(['asset', 'stockLevel', 'line']);
        });
    }

    private function nullableNotes(?string $notes): ?string
    {
        $notes = $notes !== null ? trim($notes) : null;

        return $notes === '' ? null : $notes;
    }
}
