<?php

namespace App\Actions\Products;

use App\Data\Products\StockLevelData;
use App\Data\Products\UpdateStockLevelData;
use App\Enums\StockCategory;
use App\Events\AuditableEvent;
use App\Models\StockLevel;
use App\Services\Api\WebhookService;
use App\Services\Availability\ProductStoreCapacityLock;
use App\Services\CustomFieldValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class UpdateStockLevel
{
    /**
     * Update an existing stock level.
     *
     * Field update convention (Spatie Data Optional behaviour):
     *   - Absent key / `null` in DTO → field is not updated (retains current value)
     *   - Empty string `""` → field is cleared (set to null in database)
     */
    public function __invoke(StockLevel $stockLevel, UpdateStockLevelData $data): StockLevelData
    {
        Gate::authorize('stock.adjust');
        Gate::authorize('access', $stockLevel);

        $attributes = collect($data->toArray())
            ->except(['custom_fields'])
            ->reject(fn ($value) => $value === null)
            ->map(fn ($value) => $value === '' ? null : $value);

        foreach (['quantity_held', 'quantity_allocated', 'quantity_unavailable'] as $protectedQuantity) {
            if ($attributes->has($protectedQuantity)) {
                throw ValidationException::withMessages([
                    $protectedQuantity => 'Physical and projection quantities must be changed through their dedicated workflows.',
                ]);
            }
        }

        if ($stockLevel->asset_id !== null) {
            if ($attributes->has('product_id')
                && (int) $attributes->get('product_id') !== (int) $stockLevel->product_id) {
                throw ValidationException::withMessages([
                    'product_id' => 'A stock level linked to an Asset cannot change Product.',
                ]);
            }

            if ($attributes->has('stock_category')
                && (int) $attributes->get('stock_category') !== StockCategory::SerialisedStock->value) {
                throw ValidationException::withMessages([
                    'stock_category' => 'A stock level linked to an Asset must remain serialised.',
                ]);
            }

            if ($attributes->has('is_virtual') && (bool) $attributes->get('is_virtual')) {
                throw ValidationException::withMessages([
                    'is_virtual' => 'A stock level linked to an Asset cannot become virtual.',
                ]);
            }
        }

        // Asset/barcode and serial numbers are globally unique (ignoring self).
        $assetNumber = $attributes->get('asset_number');
        $serialNumber = $attributes->get('serial_number');
        StockLevel::assertUniqueIdentifiers(
            is_string($assetNumber) ? $assetNumber : null,
            is_string($serialNumber) ? $serialNumber : null,
            $stockLevel->id,
        );

        return DB::transaction(function () use ($stockLevel, $attributes, $data): StockLevelData {
            $newProductId = (int) ($attributes->get('product_id') ?? $stockLevel->product_id);
            $newStoreId = (int) ($attributes->get('store_id') ?? $stockLevel->store_id);
            Gate::authorize('access', new StockLevel(['store_id' => $newStoreId]));
            app(ProductStoreCapacityLock::class)->acquireMany([
                ['product_id' => (int) $stockLevel->product_id, 'store_id' => (int) $stockLevel->store_id],
                ['product_id' => $newProductId, 'store_id' => $newStoreId],
            ]);

            $stockLevel->update($attributes->all());

            if ($data->custom_fields !== null) {
                app(CustomFieldValidator::class)->validate('StockLevel', $data->custom_fields);
                $stockLevel->syncCustomFields($data->custom_fields);
            }

            $stockLevel->refresh();

            event(new AuditableEvent($stockLevel, 'stock_level.updated'));

            app(WebhookService::class)->dispatch('stock_level.updated', [
                'stock_level' => StockLevelData::fromModel($stockLevel)->toArray(),
            ]);

            return StockLevelData::fromModel($stockLevel);
        });
    }
}
