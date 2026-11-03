<?php

declare(strict_types=1);

namespace App\Domain\Stock\Stocktakes\Actions;

use App\Domain\Stock\Models\StockLevel;
use App\Domain\Stock\Stocktakes\Data\CreateStocktakeData;
use App\Domain\Stock\Stocktakes\Enums\StocktakeStatus;
use App\Domain\Stock\Stocktakes\Models\Stocktake;
use App\Domain\Stores\Models\Store;
use Illuminate\Support\Facades\Gate;

final class CreateStocktake
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
