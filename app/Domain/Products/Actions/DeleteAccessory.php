<?php

declare(strict_types=1);

namespace App\Domain\Products\Actions;

use App\Domain\AuditLog\Events\AuditableEvent;
use App\Domain\Products\Models\Accessory;
use App\Domain\Webhooks\Services\WebhookService;
use Illuminate\Support\Facades\Gate;

class DeleteAccessory
{
    public function __invoke(Accessory $accessory): void
    {
        Gate::authorize('products.edit');

        event(new AuditableEvent($accessory, 'accessory.deleted'));

        // Delete webhooks send only the ID as the resource no longer exists.
        app(WebhookService::class)->dispatch('product.updated', [
            'product_id' => $accessory->product_id,
        ]);

        $accessory->delete();
    }
}
