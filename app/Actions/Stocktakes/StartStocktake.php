<?php

namespace App\Actions\Stocktakes;

use App\Enums\StockCategory;
use App\Enums\StockMethod;
use App\Enums\StocktakeStatus;
use App\Models\ContainerItem;
use App\Models\StockLevel;
use App\Models\Stocktake;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class StartStocktake
{
    public function __invoke(Stocktake $stocktake): Stocktake
    {
        Gate::authorize('stock.count');
        Gate::authorize('access', new StockLevel(['store_id' => $stocktake->store_id]));

        /** @var User $user */
        $user = auth()->user();

        return DB::transaction(function () use ($stocktake, $user): Stocktake {
            $locked = Stocktake::query()->lockForUpdate()->findOrFail($stocktake->id);

            if (! $locked->isDraft()) {
                throw ValidationException::withMessages([
                    'stocktake' => 'Only a draft Stocktake can be started.',
                ]);
            }

            $levels = StockLevel::query()
                ->with('product:id,name,stock_method')
                ->where('store_id', $locked->store_id)
                ->where('is_virtual', false)
                ->whereNull('asset_id')
                ->whereNull('container_stock_level_id')
                ->where('stock_category', StockCategory::BulkStock->value)
                ->whereHas('product', fn ($query) => $query->where('stock_method', '!=', StockMethod::Serialised->value))
                ->orderBy('id')
                ->get();

            $serializedLevels = StockLevel::query()
                ->with(['product:id,name,stock_method', 'asset:id,lifecycle_status'])
                ->where('store_id', $locked->store_id)
                ->where('is_virtual', false)
                ->whereNotNull('asset_id')
                ->where('stock_category', StockCategory::SerialisedStock->value)
                ->whereHas('product', fn ($query) => $query->where('stock_method', StockMethod::Serialised->value))
                ->orderBy('id')
                ->get();

            $containerItems = ContainerItem::query()
                ->with('container:id,uuid,name,barcode')
                ->whereIn('serialised_item_id', $serializedLevels->pluck('id'))
                ->whereNull('unpacked_at')
                ->get()
                ->keyBy('serialised_item_id');

            foreach ($levels as $level) {
                $locked->lines()->create([
                    'stock_level_id' => $level->id,
                    'product_name_snapshot' => $level->product->name ?? $level->item_name ?? 'Stock item',
                    'stock_level_label_snapshot' => $level->item_name,
                    'quantity_held_snapshot' => $level->quantity_held,
                ]);
            }

            foreach ($serializedLevels as $level) {
                $container = $containerItems->get($level->id)?->container;
                $asset = $level->asset?->loadMissing(['repairs', 'inspections', 'usages']);
                $repair = $asset?->repairs->sortByDesc('id')->first();
                $inspection = $asset?->inspections->sortByDesc('id')->first();
                $usage = $asset?->usages->whereNull('returned_at')->sortByDesc('id')->first();

                $locked->lines()->create([
                    'stock_level_id' => $level->id,
                    'asset_id' => $level->asset_id,
                    'product_name_snapshot' => $level->product->name ?? $level->item_name ?? 'Serialized item',
                    'stock_level_label_snapshot' => $level->item_name,
                    'quantity_held_snapshot' => $level->quantity_held,
                    'asset_number_snapshot' => $level->asset_number,
                    'serial_number_snapshot' => $level->serial_number,
                    'barcode_snapshot' => $level->barcode,
                    'lifecycle_status_snapshot' => $asset?->lifecycleStatus()->value,
                    'repair_status_snapshot' => $repair?->getRawOriginal('status'),
                    'inspection_status_snapshot' => $inspection?->getRawOriginal('status'),
                    'asset_usage_id_snapshot' => $usage?->id,
                    'container_id' => $container?->id,
                    'container_name_snapshot' => $container?->name,
                    'container_barcode_snapshot' => $container?->barcode,
                ]);
            }

            $locked->forceFill([
                'status' => StocktakeStatus::InProgress,
                'started_by_user_id' => $user->id,
                'started_at' => now('UTC'),
            ])->save();

            return $locked->fresh(['store', 'startedBy', 'lines.stockLevel.product']);
        });
    }
}
