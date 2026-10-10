<?php

namespace App\Actions\Products;

use App\Models\StockTransaction;

class DeleteStockTransaction
{
    public function __invoke(StockTransaction $transaction, ?string $reason = null): void
    {
        app(ReverseStockTransaction::class)($transaction, $reason ?? '');
    }
}
