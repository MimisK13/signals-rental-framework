<?php

namespace App\Verbs\Events\PurchaseOrders;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Verbs\States\PurchaseOrderState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

/**
 * Genesis event for a purchase order. Creates the state as a draft document and
 * projects the initial purchase_orders row plus optional line rows.
 */
class PurchaseOrderCreated extends Event
{
    public function __construct(
        public int $purchase_order_id,
        #[StateId(PurchaseOrderState::class)]
        public ?int $state_id = null,
        public string $number = '',
        public string $po_type = 'purchase',
        public string $status = 'draft',
        public int $supplier_id = 0,
        public int $store_id = 0,
        public ?int $opportunity_id = null,
        public ?int $shortage_resolution_id = null,
        public ?string $currency_code = null,
        public ?string $exchange_rate = null,
        public int $subtotal = 0,
        public int $tax_total = 0,
        public int $total = 0,
        public int $rounding_adjustment = 0,
        public ?string $expected_at = null,
        public ?string $starts_at = null,
        public ?string $ends_at = null,
        public ?string $reference = null,
        public ?string $notes = null,
        public ?string $supplier_reference = null,
        public ?int $created_by = null,
        public ?string $created_at = null,
        /** @var list<array<string, mixed>> */
        public array $line_items = [],
    ) {}

    public function apply(PurchaseOrderState $state): void
    {
        $state->purchase_order_id = $this->purchase_order_id;
        $state->number = $this->number;
        $state->po_type = $this->po_type;
        $state->status = $this->status;
        $state->supplier_id = $this->supplier_id;
        $state->store_id = $this->store_id;
        $state->opportunity_id = $this->opportunity_id;
        $state->shortage_resolution_id = $this->shortage_resolution_id;
        $state->currency_code = $this->currency_code ?? 'GBP';
        $state->exchange_rate = $this->exchange_rate ?? '1';
        $state->subtotal = $this->subtotal;
        $state->tax_total = $this->tax_total;
        $state->total = $this->total;
        $state->rounding_adjustment = $this->rounding_adjustment;
        $state->expected_at = $this->expected_at !== null
            ? CarbonImmutable::parse($this->expected_at)
            : null;
        $state->starts_at = $this->starts_at !== null
            ? CarbonImmutable::parse($this->starts_at)
            : null;
        $state->ends_at = $this->ends_at !== null
            ? CarbonImmutable::parse($this->ends_at)
            : null;
        $state->reference = $this->reference;
        $state->notes = $this->notes;
        $state->supplier_reference = $this->supplier_reference;
        $state->created_by = $this->created_by;
        $state->created_at = $this->created_at !== null
            ? CarbonImmutable::parse($this->created_at)
            : null;
        $state->last_event_at = $state->created_at;
    }

    public function handle(PurchaseOrderState $state): void
    {
        PurchaseOrder::withTrashed()->updateOrCreate(
            ['id' => $state->purchase_order_id],
            [
                'state_id' => $state->id,
                'number' => $state->number,
                'po_type' => $state->po_type,
                'status' => $state->status,
                'supplier_id' => $state->supplier_id,
                'store_id' => $state->store_id,
                'opportunity_id' => $state->opportunity_id,
                'shortage_resolution_id' => $state->shortage_resolution_id,
                'subtotal' => $state->subtotal,
                'tax_total' => $state->tax_total,
                'total' => $state->total,
                'rounding_adjustment' => $state->rounding_adjustment,
                'currency_code' => $state->currency_code,
                'exchange_rate' => $state->exchange_rate,
                'expected_at' => $state->expected_at,
                'starts_at' => $state->starts_at,
                'ends_at' => $state->ends_at,
                'reference' => $state->reference,
                'notes' => $state->notes,
                'supplier_reference' => $state->supplier_reference,
                'created_by' => $state->created_by,
                'created_at' => $state->created_at,
                'updated_at' => $state->created_at,
            ],
        );

        foreach ($this->line_items as $line) {
            PurchaseOrderItem::query()->updateOrCreate(
                ['id' => (int) $line['purchase_order_item_id']],
                [
                    'purchase_order_id' => $state->purchase_order_id,
                    'product_id' => ($line['product_id'] ?? null) > 0 ? (int) $line['product_id'] : null,
                    'description' => (string) $line['description'],
                    'quantity' => (string) $line['quantity'],
                    'received_quantity' => (string) ($line['received_quantity'] ?? '0'),
                    'unit_cost' => (int) $line['unit_cost'],
                    'tax' => (int) ($line['tax'] ?? 0),
                    'product_tax_class_id' => ($line['product_tax_class_id'] ?? 0) > 0
                        ? (int) $line['product_tax_class_id']
                        : null,
                    'total' => (int) $line['total'],
                    'supplier_cost_id' => ($line['supplier_cost_id'] ?? null) > 0
                        ? (int) $line['supplier_cost_id']
                        : null,
                    'sort_order' => (int) ($line['sort_order'] ?? 0),
                    'created_at' => $state->created_at,
                    'updated_at' => $state->created_at,
                ],
            );
        }
    }
}
