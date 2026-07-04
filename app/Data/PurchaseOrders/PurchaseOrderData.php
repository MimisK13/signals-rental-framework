<?php

namespace App\Data\PurchaseOrders;

use App\Data\Concerns\EntityReferenceData;
use App\Data\Concerns\FormatsTimestamps;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Store;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Lazy;

class PurchaseOrderData extends Data
{
    use FormatsTimestamps;

    public function __construct(
        public int $id,
        public string $number,
        public string $po_type,
        public string $po_type_label,
        public string $status,
        public string $status_label,
        public int $supplier_id,
        public int $store_id,
        public ?int $opportunity_id,
        public ?int $shortage_resolution_id,
        public string $subtotal,
        public string $tax_total,
        public string $total,
        public string $rounding_adjustment,
        public string $currency_code,
        public string $exchange_rate,
        public ?string $expected_at,
        public ?string $starts_at,
        public ?string $ends_at,
        public ?string $reference,
        public ?string $notes,
        public ?string $supplier_reference,
        public ?string $sent_at,
        public ?string $received_at,
        public ?string $cancelled_at,
        public ?int $created_by,
        public ?string $created_at,
        public ?string $updated_at,
        public Lazy|EntityReferenceData|null $supplier = null,
        public Lazy|EntityReferenceData|null $store = null,
        public Lazy|EntityReferenceData|null $opportunity = null,
        /** @var Lazy|array<int, PurchaseOrderItemData> */
        public Lazy|array $items = [],
    ) {}

    public static function fromModel(PurchaseOrder $purchaseOrder): self
    {
        $typeLabel = (string) (config('purchase_orders.types.'.$purchaseOrder->po_type.'.label') ?? $purchaseOrder->po_type);
        $statusLabel = (string) (config('purchase_orders.statuses.'.$purchaseOrder->status.'.label') ?? $purchaseOrder->status);

        return new self(
            id: $purchaseOrder->id,
            number: $purchaseOrder->number,
            po_type: $purchaseOrder->po_type,
            po_type_label: $typeLabel,
            status: $purchaseOrder->status,
            status_label: $statusLabel,
            supplier_id: $purchaseOrder->supplier_id,
            store_id: $purchaseOrder->store_id,
            opportunity_id: $purchaseOrder->opportunity_id,
            shortage_resolution_id: $purchaseOrder->shortage_resolution_id,
            subtotal: $purchaseOrder->formatMoneyCost('subtotal'),
            tax_total: $purchaseOrder->formatMoneyCost('tax_total'),
            total: $purchaseOrder->formatMoneyCost('total'),
            rounding_adjustment: $purchaseOrder->formatMoneyCost('rounding_adjustment'),
            currency_code: $purchaseOrder->currency_code,
            exchange_rate: $purchaseOrder->exchange_rate,
            expected_at: self::formatNullableTimestamp($purchaseOrder->expected_at),
            starts_at: self::formatNullableTimestamp($purchaseOrder->starts_at),
            ends_at: self::formatNullableTimestamp($purchaseOrder->ends_at),
            reference: $purchaseOrder->reference,
            notes: $purchaseOrder->notes,
            supplier_reference: $purchaseOrder->supplier_reference,
            sent_at: self::formatNullableTimestamp($purchaseOrder->sent_at),
            received_at: self::formatNullableTimestamp($purchaseOrder->received_at),
            cancelled_at: self::formatNullableTimestamp($purchaseOrder->cancelled_at),
            created_by: $purchaseOrder->created_by,
            created_at: self::formatNullableTimestamp($purchaseOrder->created_at),
            updated_at: self::formatNullableTimestamp($purchaseOrder->updated_at),
            supplier: Lazy::whenLoaded('supplier', $purchaseOrder, fn (): ?EntityReferenceData => self::reference($purchaseOrder->supplier)),
            store: Lazy::whenLoaded('store', $purchaseOrder, fn (): ?EntityReferenceData => self::reference($purchaseOrder->store)),
            opportunity: Lazy::whenLoaded('opportunity', $purchaseOrder, fn (): ?EntityReferenceData => self::reference($purchaseOrder->opportunity)),
            items: Lazy::whenLoaded('items', $purchaseOrder, fn (): array => $purchaseOrder->items
                ->map(fn (PurchaseOrderItem $item): PurchaseOrderItemData => PurchaseOrderItemData::fromModel($item))
                ->values()
                ->all()),
        );
    }

    private static function reference(Member|Store|Opportunity|null $model): ?EntityReferenceData
    {
        if ($model === null) {
            return null;
        }

        $name = match (true) {
            $model instanceof Opportunity => $model->subject,
            default => $model->name ?? (string) $model->id,
        };

        return new EntityReferenceData(
            id: $model->id,
            name: $name,
        );
    }
}
