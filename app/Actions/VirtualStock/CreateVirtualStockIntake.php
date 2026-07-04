<?php

namespace App\Actions\VirtualStock;

use App\Concerns\CommitsVerbsEvents;
use App\Data\VirtualStock\CreateVirtualStockIntakeData;
use App\Data\VirtualStock\VirtualStockIntakeData;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\VirtualStockIntake;
use App\Services\CurrencyService;
use App\Services\SequenceAllocator;
use App\Services\VirtualStock\VirtualStockNumberGenerator;
use App\Services\VirtualStock\VirtualStockStatusRegistry;
use App\Verbs\Events\VirtualStock\VirtualStockIntakeCreated;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CreateVirtualStockIntake
{
    use CommitsVerbsEvents;

    public function __invoke(CreateVirtualStockIntakeData $data): VirtualStockIntakeData
    {
        Gate::authorize('create', VirtualStockIntake::class);

        [$productId, $storeId, $quantity, $unitCost, $currency, $poId, $poItemId] = $this->resolveFields($data);

        $status = app(VirtualStockStatusRegistry::class)->defaultStatus();
        app(VirtualStockStatusRegistry::class)->assertKnown($status);

        $createdAt = CarbonImmutable::now('UTC');
        $allocator = app(SequenceAllocator::class);

        $intakeId = $this->commitVerbs(function () use (
            $data,
            $productId,
            $storeId,
            $quantity,
            $unitCost,
            $currency,
            $poId,
            $poItemId,
            $status,
            $createdAt,
            $allocator,
        ): int {
            $intakeId = $allocator->next('virtual_stock_intakes');

            $number = app(VirtualStockNumberGenerator::class)->nextOrCreate(
                $storeId,
                $createdAt,
            );

            VirtualStockIntakeCreated::fire(
                virtual_stock_intake_id: $intakeId,
                number: $number,
                status: $status,
                purchase_order_id: $poId,
                purchase_order_item_id: $poItemId,
                product_id: $productId,
                store_id: $storeId,
                quantity: $quantity,
                available_from: $data->available_from,
                available_until: $data->available_until,
                unit_cost: $unitCost,
                currency_code: $currency,
                notes: $data->notes,
                created_by: auth()->id(),
                created_at: $createdAt->toIso8601String(),
            );

            return $intakeId;
        });

        return VirtualStockIntakeData::fromModel(
            VirtualStockIntake::query()->whereKey($intakeId)->firstOrFail(),
        );
    }

    /**
     * @return array{0: int, 1: int, 2: int, 3: int, 4: string, 5: ?int, 6: ?int}
     */
    private function resolveFields(CreateVirtualStockIntakeData $data): array
    {
        $productId = $data->product_id;
        $storeId = $data->store_id;
        $quantity = $data->quantity;
        $unitCost = $data->unit_cost;
        $currency = $this->resolveCurrency($data->currency_code);
        $poId = $data->purchase_order_id;
        $poItemId = $data->purchase_order_item_id;

        if ($poItemId !== null) {
            $line = PurchaseOrderItem::query()->with('purchaseOrder')->findOrFail($poItemId);
            $po = $line->purchaseOrder ?? PurchaseOrder::query()->findOrFail($line->purchase_order_id);

            if ($po->po_type !== 'sub_hire') {
                throw ValidationException::withMessages([
                    'purchase_order_item_id' => ['Virtual stock intakes can only be created from sub-hire purchase order lines.'],
                ]);
            }

            if ($poId !== null && $poId !== $po->id) {
                throw ValidationException::withMessages([
                    'purchase_order_id' => ['The purchase order does not match the selected line.'],
                ]);
            }

            $poId = $po->id;

            if ($line->product_id === null) {
                throw ValidationException::withMessages([
                    'purchase_order_item_id' => ['The purchase order line must reference a product.'],
                ]);
            }

            $productId = $line->product_id;
            $storeId = $po->store_id;
            $quantity = max(1, (int) round((float) $line->quantity));
            $unitCost = $line->unit_cost;
            $currency = $po->currency_code;
        }

        return [$productId, $storeId, $quantity, $unitCost, $currency, $poId, $poItemId];
    }

    private function resolveCurrency(?string $currency): string
    {
        if ($currency !== null && $currency !== '') {
            return strtoupper($currency);
        }

        return app(CurrencyService::class)->baseCurrencyCode();
    }
}
