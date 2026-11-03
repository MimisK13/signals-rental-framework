<?php

declare(strict_types=1);

namespace App\Domain\Products\Actions;

use App\Domain\AuditLog\Events\AuditableEvent;
use App\Domain\Products\Models\Product;
use App\Domain\Webhooks\Services\WebhookService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class DeleteProduct
{
    public function __invoke(Product $product): void
    {
        Gate::authorize('products.delete');

        DB::transaction(function () use ($product): void {
            event(new AuditableEvent($product, 'product.deleted'));

            // Delete webhooks send only the ID as the resource no longer exists.
            app(WebhookService::class)->dispatch('product.deleted', [
                'id' => $product->id,
            ]);

            $product->delete();
        });
    }
}
