<?php

declare(strict_types=1);

namespace App\Domain\Stock\Actions;

use App\Domain\Stock\Models\StockTransaction;

class DeleteStockTransaction
{
    public function __invoke(StockTransaction $transaction, ?string $reason = null): void
    {
        app(ReverseStockTransaction::class)($transaction, $reason ?? '');
    }
}
