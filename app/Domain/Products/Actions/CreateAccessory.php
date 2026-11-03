<?php

declare(strict_types=1);

namespace App\Domain\Products\Actions;

use App\Domain\AuditLog\Events\AuditableEvent;
use App\Domain\Products\Data\AccessoryData;
use App\Domain\Products\Data\CreateAccessoryData;
use App\Domain\Products\Models\Accessory;
use App\Domain\Products\Models\Product;
use App\Domain\Webhooks\Services\WebhookService;
use Illuminate\Support\Facades\Gate;

class CreateAccessory
{
    public function __invoke(CreateAccessoryData $data): AccessoryData
    {
        Gate::authorize('products.edit');

        $product = Product::findOrFail($data->product_id);

        $accessory = $product->accessories()->create([
            'accessory_product_id' => $data->accessory_product_id,
            'quantity' => $data->quantity,
            'included' => $data->included,
            'zero_priced' => $data->zero_priced,
            'sort_order' => $data->sort_order,
        ]);

        /** @var Accessory $accessory */
        $accessory->load('accessoryProduct');

        event(new AuditableEvent($accessory, 'accessory.created'));

        app(WebhookService::class)->dispatch('product.updated', [
            'product_id' => $product->id,
        ]);

        return AccessoryData::fromModel($accessory);
    }
}
