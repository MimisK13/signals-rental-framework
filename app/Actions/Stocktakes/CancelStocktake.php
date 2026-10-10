<?php

namespace App\Actions\Stocktakes;

use App\Enums\StocktakeStatus;
use App\Models\StockLevel;
use App\Models\Stocktake;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CancelStocktake
{
    public function __invoke(Stocktake $stocktake): Stocktake
    {
        Gate::authorize('stock.count');
        Gate::authorize('access', new StockLevel(['store_id' => $stocktake->store_id]));

        /** @var User $user */
        $user = auth()->user();

        return DB::transaction(function () use ($stocktake, $user): Stocktake {
            $locked = Stocktake::query()->lockForUpdate()->findOrFail($stocktake->id);

            if (! $locked->isInProgress()) {
                throw ValidationException::withMessages([
                    'stocktake' => 'Only an in-progress Stocktake can be cancelled.',
                ]);
            }

            $locked->forceFill([
                'status' => StocktakeStatus::Cancelled,
                'cancelled_by_user_id' => $user->id,
                'cancelled_at' => now('UTC'),
            ])->save();

            return $locked->fresh(['store', 'startedBy', 'cancelledBy', 'lines.acceptedObservation.countedBy']);
        });
    }
}
