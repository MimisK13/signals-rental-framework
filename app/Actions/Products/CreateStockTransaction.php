<?php

namespace App\Actions\Products;

use App\Data\Products\CreateStockTransactionData;
use App\Data\Products\StockTransactionData;
use App\Enums\TransactionType;
use App\Events\AuditableEvent;
use App\Models\StockLevel;
use App\Models\StockTransaction;
use App\Services\Api\WebhookService;
use App\Services\Availability\ProductStoreCapacityLock;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CreateStockTransaction
{
    public function __invoke(CreateStockTransactionData $data, ?Model $source = null): StockTransactionData
    {
        Gate::authorize('stock.adjust');

        $stockLevel = StockLevel::query()->findOrFail($data->stock_level_id);
        Gate::authorize('access', $stockLevel);

        return DB::transaction(function () use ($data, $source, $stockLevel): StockTransactionData {
            app(ProductStoreCapacityLock::class)->acquire($stockLevel->product_id, $stockLevel->store_id);
            $stockLevel = StockLevel::query()
                ->whereKey($data->stock_level_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($data->store_id !== null && $data->store_id !== (int) $stockLevel->store_id) {
                throw ValidationException::withMessages([
                    'store_id' => 'The transaction store must match the Stock Level store.',
                ]);
            }

            // Transactions move whole units only; serialised stock moves exactly one.
            $quantity = (float) $data->quantity;
            if ($quantity <= 0.0 || floor($quantity) !== $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => 'Transaction quantity must be a whole number.',
                ]);
            }
            if ($stockLevel->isSerialised() && $quantity !== 1.0) {
                throw ValidationException::withMessages([
                    'quantity' => 'Serialised stock can only move one unit per transaction.',
                ]);
            }

            $transactionType = TransactionType::from($data->transaction_type);
            $before = bcadd((string) $stockLevel->quantity_held, '0', 2);
            $after = bcadd($before, $transactionType->signedQuantity((string) $data->quantity), 2);

            // Quantity decreases and future bulk containment mutations share the
            // exact StockLevel row lock so owned quantity cannot cross the current
            // physical containment floor.
            if (bccomp($after, $stockLevel->containedBulkQuantity(), 2) < 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'The transaction would reduce owned stock below current bulk containment.',
                ]);
            }

            if (bccomp($after, '0', 2) < 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'The transaction would make physical stock negative.',
                ]);
            }

            $transaction = StockTransaction::create([
                'stock_level_id' => $data->stock_level_id,
                'store_id' => $stockLevel->store_id,
                'transaction_type' => $data->transaction_type,
                'transaction_at' => $data->transaction_at ?? now(),
                'quantity' => $data->quantity,
                'description' => $data->description,
                'manual' => $source === null
                    && in_array($data->transaction_type, TransactionType::adjustmentValues(), true),
                'actor_user_id' => auth()->id(),
                'quantity_before' => $before,
                'quantity_after' => $after,
                'source_id' => $source?->getKey(),
                'source_type' => $source?->getMorphClass(),
            ]);

            $stockLevel->update(['quantity_held' => $after]);

            event(new AuditableEvent($transaction, 'stock_transaction.created'));

            app(WebhookService::class)->dispatch('stock_transaction.created', [
                'stock_transaction' => StockTransactionData::fromModel($transaction)->toArray(),
            ]);

            return StockTransactionData::fromModel($transaction);
        });
    }
}
