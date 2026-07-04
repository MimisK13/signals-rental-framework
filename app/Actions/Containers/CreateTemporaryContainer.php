<?php

namespace App\Actions\Containers;

use App\Data\Containers\ContainerData;
use App\Data\Containers\CreateTemporaryContainerData;
use App\Enums\ContainerScanMode;
use App\Enums\ContainerStatus;
use App\Models\Container;
use App\Models\StockLevel;
use App\Services\Containers\ContainerEventRecorder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Create a barcode-only temporary container (serialised-containers.md §"Temporary
 * containers"). Always uses free scan mode and has no backing product/asset.
 */
class CreateTemporaryContainer
{
    public function __invoke(CreateTemporaryContainerData $data): ContainerData
    {
        Gate::authorize('createTemporary', Container::class);

        $barcode = trim($data->barcode);

        if ($barcode === '') {
            throw ValidationException::withMessages([
                'barcode' => __('The barcode is required.'),
            ]);
        }

        $this->guardUniqueBarcode($barcode);

        return DB::transaction(function () use ($data, $barcode): ContainerData {
            try {
                $container = Container::create([
                    'name' => $data->name ?? $barcode,
                    'serialised_item_id' => null,
                    'product_id' => null,
                    'is_temporary' => true,
                    'barcode' => $barcode,
                    'store_id' => $data->store_id,
                    'scan_mode' => ContainerScanMode::Free,
                    'status' => ContainerStatus::Open,
                    'notes' => $data->notes,
                ]);
            } catch (QueryException) {
                throw ValidationException::withMessages([
                    'barcode' => __('This barcode is already in use by an active container.'),
                ]);
            }

            app(ContainerEventRecorder::class)->created($container);

            return ContainerData::fromModel($container);
        });
    }

    /**
     * @throws ValidationException
     */
    protected function guardUniqueBarcode(string $barcode): void
    {
        $exists = Container::query()
            ->active()
            ->where('barcode', $barcode)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'barcode' => __('This barcode is already in use by an active container.'),
            ]);
        }

        $collidesWithSerialisedStock = StockLevel::query()
            ->serialized()
            ->where(fn ($q) => $q->where('asset_number', $barcode)->orWhere('serial_number', $barcode))
            ->exists();

        if ($collidesWithSerialisedStock) {
            throw ValidationException::withMessages([
                'barcode' => __('This barcode conflicts with an existing serialised asset identifier.'),
            ]);
        }
    }
}
