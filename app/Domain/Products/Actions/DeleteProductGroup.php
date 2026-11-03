<?php

declare(strict_types=1);

namespace App\Domain\Products\Actions;

use App\Domain\AuditLog\Events\AuditableEvent;
use App\Domain\Products\Models\ProductGroup;
use App\Domain\Webhooks\Services\WebhookService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class DeleteProductGroup
{
    public function __invoke(ProductGroup $productGroup): void
    {
        Gate::authorize('products.delete');

        DB::transaction(function () use ($productGroup): void {
            event(new AuditableEvent($productGroup, 'product_group.deleted'));

            // Delete webhooks send only the ID as the resource no longer exists.
            app(WebhookService::class)->dispatch('product_group.deleted', [
                'id' => $productGroup->id,
            ]);

            $productGroup->delete();
        });
    }
}
