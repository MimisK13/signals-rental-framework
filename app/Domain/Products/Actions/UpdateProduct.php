<?php

declare(strict_types=1);

namespace App\Domain\Products\Actions;

use App\Domain\AuditLog\Events\AuditableEvent;
use App\Domain\CustomFields\Services\CustomFieldValidator;
use App\Domain\Products\Data\ProductData;
use App\Domain\Products\Data\UpdateProductData;
use App\Domain\Products\Models\Product;
use App\Domain\Webhooks\Services\WebhookService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class UpdateProduct
{
    /**
     * Update an existing product.
     *
     * Field update convention (Spatie Data Optional behaviour):
     *   - Absent key / `null` in DTO → field is not updated (retains current value)
     *   - Empty string `""` → field is cleared (set to null in database)
     */
    public function __invoke(Product $product, UpdateProductData $data): ProductData
    {
        Gate::authorize('products.edit');

        return DB::transaction(function () use ($product, $data): ProductData {
            $product->update(
                collect($data->toArray())
                    ->except(['custom_fields'])
                    ->reject(fn ($value) => $value === null)
                    ->map(fn ($value) => $value === '' ? null : $value)
                    ->all()
            );

            if ($data->custom_fields !== null) {
                app(CustomFieldValidator::class)->validate('Product', $data->custom_fields);
                $product->syncCustomFields($data->custom_fields);
            }

            $product->refresh();

            event(new AuditableEvent($product, 'product.updated'));

            app(WebhookService::class)->dispatch('product.updated', [
                'product' => ProductData::fromModel($product)->toArray(),
            ]);

            return ProductData::fromModel($product);
        });
    }
}
