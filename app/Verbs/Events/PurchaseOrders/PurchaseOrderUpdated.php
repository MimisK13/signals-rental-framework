<?php

namespace App\Verbs\Events\PurchaseOrders;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Verbs\States\PurchaseOrderState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

/**
 * Full re-snapshot update for draft purchase orders (header + all line items).
 */
class PurchaseOrderUpdated extends Event
{
    public function __construct(
        #[StateId(PurchaseOrderState::class)]
        public int $state_id,
        public string $po_type = 'purchase',
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
        public ?string $updated_at = null,
        /** @var list<array<string, mixed>> */
        public array $line_items = [],
    ) {}

    public function validate(PurchaseOrderState $state): void
    {
        $this->assert(
            $state->status === 'draft' && ! $state->is_deleted,
            'Only draft purchase orders can be edited.',
        );
    }

    public function apply(PurchaseOrderState $state): void
    {
        $state->po_type = $this->po_type;
        $state->supplier_id = $this->supplier_id;
        $state->store_id = $this->store_id;
        $state->opportunity_id = $this->opportunity_id;
        $state->shortage_resolution_id = $this->shortage_resolution_id;
        $state->currency_code = $this->currency_code ?? $state->currency_code;
        $state->exchange_rate = $this->exchange_rate ?? $state->exchange_rate;
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
        $state->last_event_at = $this->updated_at !== null
            ? CarbonImmutable::parse($this->updated_at)
            : $state->last_event_at;
    }

    public function handle(PurchaseOrderState $state): void
    {
        $updatedAt = $state->last_event_at;

        PurchaseOrder::withTrashed()
            ->whereKey($state->purchase_order_id)
            ->update([
                'po_type' => $state->po_type,
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
                'updated_at' => $updatedAt,
            ]);

        $retainedIds = [];

        foreach ($this->line_items as $line) {
            $itemId = (int) $line['purchase_order_item_id'];
            $retainedIds[] = $itemId;

            PurchaseOrderItem::query()->updateOrCreate(
                ['id' => $itemId],
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
                    'updated_at' => $updatedAt,
                ],
            );
        }

        PurchaseOrderItem::query()
            ->where('purchase_order_id', $state->purchase_order_id)
            ->when($retainedIds !== [], fn ($query) => $query->whereNotIn('id', $retainedIds))
            ->delete();
    }
}
