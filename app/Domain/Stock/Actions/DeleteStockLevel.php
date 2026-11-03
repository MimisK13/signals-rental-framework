<?php

declare(strict_types=1);

namespace App\Domain\Stock\Actions;

use App\Domain\Availability\Services\ProductStoreCapacityLock;
use App\Domain\Stock\Enums\StockMethod;
use App\Domain\Stock\Models\StockLevel;
use App\Events\AuditableEvent;
use App\Services\Api\WebhookService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class DeleteStockLevel
{
    public function __invoke(StockLevel $stockLevel): void
    {
        Gate::authorize('stock.adjust');
        Gate::authorize('access', $stockLevel);

        if ($stockLevel->stockTransactions()->exists()) {
            throw ValidationException::withMessages([
                'stock_level' => 'A Stock Level with transaction history cannot be deleted.',
            ]);
        }

        // A bulk product must always retain at least one stock level.
        $isBulk = $stockLevel->product?->stock_method !== StockMethod::Serialised;
        if ($isBulk && StockLevel::where('product_id', $stockLevel->product_id)->count() <= 1) {
            throw ValidationException::withMessages([
                'stock_level' => 'A bulk product must keep at least one stock level.',
            ]);
        }

        DB::transaction(function () use ($stockLevel): void {
            app(ProductStoreCapacityLock::class)->acquire($stockLevel->product_id, $stockLevel->store_id);
            $stockLevel->refresh();

            if ($stockLevel->stockTransactions()->exists()) {
                throw ValidationException::withMessages([
                    'stock_level' => 'A Stock Level with transaction history cannot be deleted.',
                ]);
            }

            event(new AuditableEvent($stockLevel, 'stock_level.deleted'));

            // Delete webhooks send only the ID as the resource no longer exists.
            app(WebhookService::class)->dispatch('stock_level.deleted', [
                'id' => $stockLevel->id,
            ]);

            $stockLevel->delete();
        });
    }
}
