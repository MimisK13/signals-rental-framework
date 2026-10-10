<?php

namespace App\Actions\Products;

use App\Data\Products\StockTransactionData;
use App\Enums\TransactionType;
use App\Events\AuditableEvent;
use App\Models\StockTransaction;
use App\Services\Api\WebhookService;
use App\Services\Availability\ProductStoreCapacityLock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ReverseStockTransaction
{
    public function __invoke(StockTransaction $original, string $reason): StockTransactionData
    {
        Gate::authorize('stock.adjust');

        $original->loadMissing('stockLevel');
        Gate::authorize('access', $original->stockLevel);

        if (trim($reason) === '') {
            throw ValidationException::withMessages([
                'reason' => 'A reversal reason is required.',
            ]);
        }

        return DB::transaction(function () use ($original, $reason): StockTransactionData {
            $stockLevel = $original->stockLevel;
            app(ProductStoreCapacityLock::class)->acquire($stockLevel->product_id, $stockLevel->store_id);

            $stockLevel = $stockLevel->newQuery()
                ->whereKey($stockLevel->id)
                ->lockForUpdate()
                ->firstOrFail();
            $lockedOriginal = StockTransaction::query()
                ->whereKey($original->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedOriginal->reversal_of_id !== null) {
                throw ValidationException::withMessages([
                    'transaction' => 'A reversal transaction cannot itself be reversed.',
                ]);
            }

            if (StockTransaction::query()->where('reversal_of_id', $lockedOriginal->id)->exists()) {
                throw ValidationException::withMessages([
                    'transaction' => 'This transaction has already been reversed.',
                ]);
            }

            $before = bcadd((string) $stockLevel->quantity_held, '0', 2);
            $signedOriginal = $lockedOriginal->signedQuantity();
            $after = bcsub($before, $signedOriginal, 2);

            // Reversals are quantity mutations too; they must respect the same
            // containment floor while the exact StockLevel is locked.
            if (bccomp($after, $stockLevel->containedBulkQuantity(), 2) < 0) {
                throw ValidationException::withMessages([
                    'transaction' => 'The reversal would reduce owned stock below current bulk containment.',
                ]);
            }

            if (bccomp($after, '0', 2) < 0) {
                throw ValidationException::withMessages([
                    'transaction' => 'The reversal would make physical stock negative.',
                ]);
            }

            $reversalType = $signedOriginal[0] === '-'
                ? TransactionType::Increase
                : TransactionType::Decrease;

            $reversal = StockTransaction::create([
                'stock_level_id' => $stockLevel->id,
                'store_id' => $stockLevel->store_id,
                'transaction_type' => $reversalType,
                'transaction_at' => now(),
                'quantity' => $lockedOriginal->quantity,
                'description' => sprintf('Reversal of transaction #%d: %s', $lockedOriginal->id, trim($reason)),
                'manual' => true,
                'actor_user_id' => auth()->id(),
                'quantity_before' => $before,
                'quantity_after' => $after,
                'reversal_of_id' => $lockedOriginal->id,
            ]);

            $stockLevel->update(['quantity_held' => $after]);

            event(new AuditableEvent($reversal, 'stock_transaction.reversed'));
            app(WebhookService::class)->dispatch('stock_transaction.reversed', [
                'stock_transaction' => StockTransactionData::fromModel($reversal)->toArray(),
                'reversal_of_id' => $lockedOriginal->id,
            ]);

            return StockTransactionData::fromModel($reversal);
        });
    }
}
