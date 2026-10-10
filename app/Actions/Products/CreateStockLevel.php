<?php

namespace App\Actions\Products;

use App\Data\Products\CreateStockLevelData;
use App\Data\Products\StockLevelData;
use App\Enums\StockCategory;
use App\Enums\StockMethod;
use App\Enums\TransactionType;
use App\Events\AuditableEvent;
use App\Models\Asset;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockTransaction;
use App\Services\Api\WebhookService;
use App\Services\Availability\ProductStoreCapacityLock;
use App\Services\CustomFieldValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CreateStockLevel
{
    public function __invoke(CreateStockLevelData $data): StockLevelData
    {
        return $this->create($data, 'stock.adjust');
    }

    public function forContainerOnboarding(CreateStockLevelData $data): StockLevelData
    {
        // Container onboarding owns the durable-housing workflow. Its
        // containers.create permission authorizes this composed inventory
        // creation, while direct StockLevel creation remains stock.adjust.
        return $this->create($data, 'containers.create');
    }

    private function create(CreateStockLevelData $data, string $permission): StockLevelData
    {
        Gate::authorize($permission);

        Gate::authorize('access', new StockLevel(['store_id' => $data->store_id]));

        // Bulk products may only ever have a single stock level.
        $product = Product::find($data->product_id);
        if ($product && $product->stock_method !== StockMethod::Serialised
            && StockLevel::where('product_id', $data->product_id)->exists()) {
            throw ValidationException::withMessages([
                'product_id' => 'Bulk products can only have a single stock level.',
            ]);
        }

        // Asset/barcode and serial numbers are globally unique.
        StockLevel::assertUniqueIdentifiers($data->asset_number, $data->serial_number);

        app(CustomFieldValidator::class)->validate('StockLevel', $data->custom_fields, enforceRequired: true);

        // The stock category is a function of the parent product's stock method:
        // serialised products track serialised stock, everything else is bulk.
        // Deriving it here keeps the discriminator correct for every caller
        // (web form, bulk serialised entry, API, product creation).
        $stockCategory = $product && $product->stock_method === StockMethod::Serialised
            ? StockCategory::SerialisedStock->value
            : StockCategory::BulkStock->value;

        if ($stockCategory === StockCategory::SerialisedStock->value
            && bccomp((string) $data->quantity_held, '1', 2) !== 0) {
            throw ValidationException::withMessages([
                'quantity_held' => 'Serialised stock levels must have a quantity of exactly 1.',
            ]);
        }

        return DB::transaction(function () use ($data, $stockCategory): StockLevelData {
            app(ProductStoreCapacityLock::class)->acquire($data->product_id, $data->store_id);

            $stockLevel = StockLevel::create([
                'product_id' => $data->product_id,
                'store_id' => $data->store_id,
                'member_id' => $data->member_id,
                'item_name' => $data->item_name,
                'asset_number' => $data->asset_number,
                'serial_number' => $data->serial_number,
                'barcode' => $data->barcode,
                'location' => $data->location,
                'stock_type' => $data->stock_type,
                'stock_category' => $stockCategory,
                'quantity_held' => $data->quantity_held,
                'quantity_allocated' => $data->quantity_allocated,
                'quantity_unavailable' => $data->quantity_unavailable,
                'quantity_on_order' => $data->quantity_on_order,
                'container_stock_level_id' => $data->container_stock_level_id,
                'container_mode' => $data->container_mode,
                'starts_at' => $data->starts_at,
                'ends_at' => $data->ends_at,
            ]);

            if ($stockCategory === StockCategory::SerialisedStock->value) {
                $asset = Asset::create([
                    'product_id' => $stockLevel->product_id,
                    'ownership_type' => 'unknown',
                ]);

                $stockLevel->forceFill(['asset_id' => $asset->id])->saveQuietly();
            }

            $stockLevel->syncCustomFields($data->custom_fields, applyDefaults: true);

            // Record the initial quantity as an Opening transaction so the held
            // quantity always equals the sum of its (non-deleted) transactions.
            if ((float) $stockLevel->quantity_held !== 0.0) {
                StockTransaction::create([
                    'stock_level_id' => $stockLevel->id,
                    'store_id' => $stockLevel->store_id,
                    'transaction_type' => TransactionType::Opening->value,
                    'transaction_at' => now(),
                    'quantity' => $stockLevel->quantity_held,
                    'quantity_before' => '0.00',
                    'quantity_after' => $stockLevel->quantity_held,
                    'actor_user_id' => auth()->id(),
                    'description' => 'Opening balance',
                    'manual' => false,
                ]);
            }

            event(new AuditableEvent($stockLevel, 'stock_level.created'));

            app(WebhookService::class)->dispatch('stock_level.created', [
                'stock_level' => StockLevelData::fromModel($stockLevel)->toArray(),
            ]);

            return StockLevelData::fromModel($stockLevel);
        });
    }
}
