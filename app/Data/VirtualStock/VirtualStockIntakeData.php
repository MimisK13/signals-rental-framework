<?php

namespace App\Data\VirtualStock;

use App\Data\Concerns\EntityReferenceData;
use App\Data\Concerns\FormatsTimestamps;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Store;
use App\Models\VirtualStockIntake;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Lazy;

class VirtualStockIntakeData extends Data
{
    use FormatsTimestamps;

    public function __construct(
        public int $id,
        public string $number,
        public string $status,
        public string $status_label,
        public ?int $purchase_order_id,
        public ?int $purchase_order_item_id,
        public int $product_id,
        public int $store_id,
        public int $quantity,
        public string $available_from,
        public string $available_until,
        public ?string $confirmed_at,
        public ?string $received_at,
        public ?string $returned_at,
        public ?string $cancelled_at,
        public bool $is_late_return,
        public bool $is_overrun,
        public int $allocations_count,
        public string $unit_cost,
        public string $currency_code,
        public ?int $demand_id,
        public ?string $notes,
        public ?int $created_by,
        public ?string $created_at,
        public ?string $updated_at,
        public Lazy|EntityReferenceData|null $product = null,
        public Lazy|EntityReferenceData|null $store = null,
        public Lazy|EntityReferenceData|null $purchase_order = null,
        public Lazy|EntityReferenceData|null $purchase_order_item = null,
    ) {}

    public static function fromModel(VirtualStockIntake $intake): self
    {
        $statusLabel = (string) (config('virtual_stock.statuses.'.$intake->status.'.label') ?? $intake->status);

        return new self(
            id: $intake->id,
            number: $intake->number,
            status: $intake->status,
            status_label: $statusLabel,
            purchase_order_id: $intake->purchase_order_id,
            purchase_order_item_id: $intake->purchase_order_item_id,
            product_id: $intake->product_id,
            store_id: $intake->store_id,
            quantity: $intake->quantity,
            available_from: self::formatNullableTimestamp($intake->available_from) ?? '',
            available_until: self::formatNullableTimestamp($intake->available_until) ?? '',
            confirmed_at: self::formatNullableTimestamp($intake->confirmed_at),
            received_at: self::formatNullableTimestamp($intake->received_at),
            returned_at: self::formatNullableTimestamp($intake->returned_at),
            cancelled_at: self::formatNullableTimestamp($intake->cancelled_at),
            is_late_return: $intake->isLateReturn(),
            is_overrun: $intake->isOverrun(),
            allocations_count: $intake->allocationsCount(),
            unit_cost: $intake->formatMoneyCost('unit_cost'),
            currency_code: $intake->currency_code,
            demand_id: $intake->demand_id,
            notes: $intake->notes,
            created_by: $intake->created_by,
            created_at: self::formatNullableTimestamp($intake->created_at),
            updated_at: self::formatNullableTimestamp($intake->updated_at),
            product: Lazy::whenLoaded('product', $intake, fn (): ?EntityReferenceData => self::reference($intake->product)),
            store: Lazy::whenLoaded('store', $intake, fn (): ?EntityReferenceData => self::reference($intake->store)),
            purchase_order: Lazy::whenLoaded('purchaseOrder', $intake, fn (): ?EntityReferenceData => self::referencePo($intake->purchaseOrder)),
            purchase_order_item: Lazy::whenLoaded('purchaseOrderItem', $intake, fn (): ?EntityReferenceData => self::referencePoItem($intake->purchaseOrderItem)),
        );
    }

    private static function reference(Product|Store|null $model): ?EntityReferenceData
    {
        if ($model === null) {
            return null;
        }

        return new EntityReferenceData(
            id: $model->id,
            name: $model->name ?? (string) $model->id,
        );
    }

    private static function referencePo(?PurchaseOrder $model): ?EntityReferenceData
    {
        if ($model === null) {
            return null;
        }

        return new EntityReferenceData(id: $model->id, name: $model->number);
    }

    private static function referencePoItem(?PurchaseOrderItem $model): ?EntityReferenceData
    {
        if ($model === null) {
            return null;
        }

        return new EntityReferenceData(id: $model->id, name: $model->description);
    }
}
