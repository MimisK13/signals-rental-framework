<?php

declare(strict_types=1);

namespace App\Domain\Products\Actions;

use App\Domain\AuditLog\Events\AuditableEvent;
use App\Domain\Products\Data\AccessoryData;
use App\Domain\Products\Data\UpdateAccessoryData;
use App\Domain\Products\Models\Accessory;
use App\Domain\Webhooks\Services\WebhookService;
use Illuminate\Support\Facades\Gate;

class UpdateAccessory
{
    public function __invoke(Accessory $accessory, UpdateAccessoryData $data): AccessoryData
    {
        Gate::authorize('products.edit');

        $accessory->update(
            collect($data->toArray())
                ->reject(fn ($value) => $value === null)
                ->all()
        );

        $accessory->refresh();
        $accessory->load('accessoryProduct');

        event(new AuditableEvent($accessory, 'accessory.updated'));

        app(WebhookService::class)->dispatch('product.updated', [
            'product_id' => $accessory->product_id,
        ]);

        return AccessoryData::fromModel($accessory);
    }
}
