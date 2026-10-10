<?php

namespace App\Actions\Products;

use App\Data\Products\AdjustStockLevelData;
use App\Data\Products\CreateStockTransactionData;
use App\Data\Products\StockTransactionData;
use App\Enums\StockMethod;
use App\Enums\TransactionType;
use App\Models\StockLevel;
use Illuminate\Validation\ValidationException;

class AdjustStockLevel
{
    public function __invoke(StockLevel $stockLevel, AdjustStockLevelData $data): StockTransactionData
    {
        if (! in_array($data->transaction_type, TransactionType::adjustmentValues(), true)) {
            throw ValidationException::withMessages([
                'transaction_type' => 'This transaction type is only available through its dedicated workflow.',
            ]);
        }

        if (preg_match('/^[1-9][0-9]*$/', trim($data->quantity)) !== 1) {
            throw ValidationException::withMessages([
                'quantity' => 'Adjustment quantity must be a positive whole number.',
            ]);
        }

        $reason = trim($data->reason);
        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'A reason is required for a stock adjustment.',
            ]);
        }

        $transactionType = TransactionType::from($data->transaction_type);

        if ($stockLevel->product?->stock_method === StockMethod::Serialised || $stockLevel->isSerialised()) {
            throw ValidationException::withMessages([
                'stock_level_id' => 'Serialized stock must be changed through its Asset lifecycle workflow.',
            ]);
        }

        if ($stockLevel->is_virtual) {
            throw ValidationException::withMessages([
                'stock_level_id' => 'Virtual stock must be changed through its virtual stock workflow.',
            ]);
        }

        if ($stockLevel->container_stock_level_id !== null) {
            throw ValidationException::withMessages([
                'stock_level_id' => 'Container-associated stock must be changed through container reconciliation.',
            ]);
        }

        return (new CreateStockTransaction)(CreateStockTransactionData::from([
            'stock_level_id' => $stockLevel->id,
            'transaction_type' => $transactionType->value,
            'quantity' => $data->quantity,
            'description' => $reason,
        ]));
    }
}
