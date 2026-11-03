<?php

declare(strict_types=1);

namespace App\Domain\Products\Actions;

use App\Domain\Products\Models\Product;
use App\Events\AuditableEvent;
use App\Services\Api\WebhookService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RestoreProduct
{
    public function __invoke(Product $product): void
    {
        Gate::authorize('products.delete');

        if (! $product->trashed()) {
            return;
        }

        DB::transaction(function () use ($product): void {
            $product->restore();

            event(new AuditableEvent($product, 'product.restored'));
        });

        // DeliverWebhook sets afterCommit = true, so each delivery only enqueues
        // after the surrounding transaction commits (and is dropped on rollback).
        // Dispatch placement relative to the transaction is therefore safe either way.
        app(WebhookService::class)->dispatch('product.restored', [
            'id' => $product->id,
        ]);
    }
}
