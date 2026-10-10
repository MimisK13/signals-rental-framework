<?php

namespace App\Actions\Containers;

use App\Actions\Products\CreateStockLevel;
use App\Data\Containers\ContainerData;
use App\Data\Containers\CreateContainerData;
use App\Data\Containers\CreateDurableContainerData;
use App\Data\Products\CreateStockLevelData;
use App\Enums\StockMethod;
use App\Models\Container;
use App\Models\Product;
use App\Models\StockLevel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CreateDurableContainer
{
    public function __construct(
        private readonly CreateStockLevel $createStockLevel,
        private readonly CreateContainer $createContainer,
    ) {}

    public function __invoke(CreateDurableContainerData $data): ContainerData
    {
        Gate::authorize('create', Container::class);
        Gate::authorize('access', new StockLevel(['store_id' => $data->store_id]));

        $product = Product::query()->findOrFail($data->product_id);

        $this->guardProduct($product);

        return DB::transaction(function () use ($data): ContainerData {
            // Durable onboarding is one business transaction. The serialized
            // identity must not survive independently if the Container overlay
            // cannot be created.
            $stockLevel = $this->createStockLevel->forContainerOnboarding(CreateStockLevelData::from([
                'product_id' => $data->product_id,
                'store_id' => $data->store_id,
                'item_name' => $data->name,
                'asset_number' => $data->asset_number,
                'serial_number' => $data->serial_number,
                'barcode' => $data->barcode,
                'quantity_held' => '1',
            ]));

            return ($this->createContainer)(CreateContainerData::from([
                'serialised_item_id' => $stockLevel->id,
                'name' => $data->name,
                'scan_mode' => $data->scan_mode,
                'notes' => $data->notes,
            ]));
        });
    }

    /**
     * @throws ValidationException
     */
    private function guardProduct(Product $product): void
    {
        if ($product->stock_method !== StockMethod::Serialised) {
            throw ValidationException::withMessages([
                'product_id' => __('Durable Containers require a serialised Product.'),
            ]);
        }

        if (! $product->isContainerable()) {
            throw ValidationException::withMessages([
                'product_id' => __('The selected Product is not containerable.'),
            ]);
        }
    }
}
