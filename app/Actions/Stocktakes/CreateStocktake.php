<?php

namespace App\Actions\Stocktakes;

use App\Data\Stocktakes\CreateStocktakeData;
use App\Enums\StocktakeStatus;
use App\Models\StockLevel;
use App\Models\Stocktake;
use App\Models\Store;
use Illuminate\Support\Facades\Gate;

class CreateStocktake
{
    public function __invoke(CreateStocktakeData $data): Stocktake
    {
        Gate::authorize('stock.count');

        $store = Store::query()->findOrFail($data->store_id);
        Gate::authorize('access', new StockLevel(['store_id' => $store->id]));

        return Stocktake::create([
            'store_id' => $store->id,
            'status' => StocktakeStatus::Draft,
            'notes' => $data->notes !== null ? trim($data->notes) : null,
        ]);
    }
}
