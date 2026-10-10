<?php

namespace App\Actions\Containers;

use App\Data\Containers\ContainerData;
use App\Data\Containers\CreateContainerData;
use App\Enums\ContainerScanMode;
use App\Enums\ContainerStatus;
use App\Models\Container;
use App\Models\Product;
use App\Models\StockLevel;
use App\Services\Containers\ContainerEventRecorder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Create a product-backed container from a serialised housing asset
 * (serialised-containers.md §"Product-backed containers").
 */
class CreateContainer
{
    public function __invoke(CreateContainerData $data): ContainerData
    {
        Gate::authorize('create', Container::class);

        $stockLevel = StockLevel::query()
            ->with('product')
            ->findOrFail($data->serialised_item_id);

        Gate::authorize('access', $stockLevel);

        $this->guard($stockLevel);

        $product = $stockLevel->product;
        if ($product === null) {
            throw ValidationException::withMessages([
                'serialised_item_id' => __('The serialised item has no product.'),
            ]);
        }

        $scanMode = $data->scan_mode !== null
            ? ContainerScanMode::from($data->scan_mode)
            : ContainerScanMode::Strict;

        return DB::transaction(function () use ($data, $stockLevel, $product, $scanMode): ContainerData {
            try {
                $container = Container::create([
                    'name' => $data->name ?? $this->defaultName($stockLevel, $product),
                    'serialised_item_id' => $stockLevel->id,
                    'product_id' => $product->id,
                    'is_temporary' => false,
                    'barcode' => $this->defaultBarcode($stockLevel),
                    'store_id' => $stockLevel->store_id,
                    'scan_mode' => $scanMode,
                    'status' => ContainerStatus::Open,
                    'notes' => $data->notes,
                ]);
            } catch (QueryException) {
                throw ValidationException::withMessages([
                    'serialised_item_id' => __('This serialised item already has an active container.'),
                ]);
            }

            app(ContainerEventRecorder::class)->created($container);

            return ContainerData::fromModel($container);
        });
    }

    /**
     * @throws ValidationException
     */
    protected function guard(StockLevel $stockLevel): void
    {
        if (! $stockLevel->isSerialised()) {
            throw ValidationException::withMessages([
                'serialised_item_id' => __('Only serialised items can be used as container housing.'),
            ]);
        }

        if (! $stockLevel->store_id) {
            throw ValidationException::withMessages([
                'serialised_item_id' => __('The serialised item must be held at a store.'),
            ]);
        }

        $product = $stockLevel->product;

        if (! $product?->is_containerable) {
            throw ValidationException::withMessages([
                'serialised_item_id' => __('The item\'s product is not containerable.'),
            ]);
        }

        $existing = Container::query()
            ->active()
            ->where('serialised_item_id', $stockLevel->id)
            ->exists();

        if ($existing) {
            throw ValidationException::withMessages([
                'serialised_item_id' => __('This serialised item already has an active container.'),
            ]);
        }

        $barcode = $this->defaultBarcode($stockLevel);

        if ($barcode === null) {
            return;
        }

        $containerConflict = Container::query()
            ->active()
            ->where('barcode', $barcode)
            ->exists();

        if ($containerConflict) {
            throw ValidationException::withMessages([
                'serialised_item_id' => __('The Container barcode is already in use.'),
            ]);
        }

        $stockConflict = StockLevel::query()
            ->serialized()
            ->whereKeyNot($stockLevel->id)
            ->where(function ($query) use ($barcode): void {
                $query
                    ->where('barcode', $barcode)
                    ->orWhere('asset_number', $barcode)
                    ->orWhere('serial_number', $barcode);
            })
            ->exists();

        if ($stockConflict) {
            throw ValidationException::withMessages([
                'serialised_item_id' => __('The Container barcode conflicts with another serialised asset identifier.'),
            ]);
        }
    }

    protected function defaultName(StockLevel $stockLevel, Product $product): string
    {
        $base = $stockLevel->item_name ?: $product->name;

        if ($stockLevel->asset_number !== null && $stockLevel->asset_number !== '') {
            return trim($base.' #'.$stockLevel->asset_number);
        }

        return $base;
    }

    protected function defaultBarcode(StockLevel $stockLevel): ?string
    {
        return $stockLevel->barcode
            ?? $stockLevel->asset_number
            ?? $stockLevel->serial_number;
    }
}
