<?php

namespace App\Verbs\Events\PurchaseOrders;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Services\PurchaseOrders\PurchaseOrderStatusRegistry;
use App\Verbs\States\PurchaseOrderState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

/**
 * Records goods received against a sent purchase order.
 *
 * Uses a single receipt event carrying the computed resulting status
 * (partially_received|received) rather than separate Received/PartiallyReceived
 * sibling events — the action computes cumulative received quantities and the
 * next status via {@see PurchaseOrderStatusRegistry} at fire-time, keeping
 * replay stable with one authoritative payload per receipt tranche.
 */
class PurchaseOrderReceiptRecorded extends Event
{
    public function __construct(
        #[StateId(PurchaseOrderState::class)]
        public int $state_id,
        /** @var list<array{purchase_order_item_id: int, received_quantity_delta: string}> */
        public array $lines = [],
        public ?string $received_at = null,
        /** @var array<int, string> */
        public array $item_received_quantities = [],
        public string $resulting_status = 'partially_received',
        public int $subtotal = 0,
        public int $tax_total = 0,
        public int $total = 0,
        public int $rounding_adjustment = 0,
        /** @var list<array{purchase_order_item_id: int, received_quantity: string, tax: int, total: int}> */
        public array $line_updates = [],
        public bool $tax_finalised = false,
    ) {}

    public function validate(PurchaseOrderState $state): void
    {
        $this->assert(
            ! $state->is_deleted,
            'Deleted purchase orders cannot receive goods.',
        );

        if ($state->status !== $this->resulting_status) {
            app(PurchaseOrderStatusRegistry::class)->assertCanTransitionTo(
                $state->status,
                $this->resulting_status,
            );
        }
    }

    public function apply(PurchaseOrderState $state): void
    {
        $state->status = $this->resulting_status;
        $state->item_received_quantities = $this->item_received_quantities;

        $receivedAt = $this->received_at !== null
            ? CarbonImmutable::parse($this->received_at)
            : null;

        if ($this->resulting_status === 'received') {
            $state->received_at = $receivedAt;
        }

        if ($this->tax_finalised) {
            $state->subtotal = $this->subtotal;
            $state->tax_total = $this->tax_total;
            $state->total = $this->total;
            $state->rounding_adjustment = $this->rounding_adjustment;
        }

        $state->last_event_at = $receivedAt;
    }

    public function handle(PurchaseOrderState $state): void
    {
        $receivedAt = $state->last_event_at;

        $headerPayload = [
            'status' => $state->status,
            'updated_at' => $receivedAt,
        ];

        if ($state->received_at !== null) {
            $headerPayload['received_at'] = $state->received_at;
        }

        if ($this->tax_finalised) {
            $headerPayload['subtotal'] = $state->subtotal;
            $headerPayload['tax_total'] = $state->tax_total;
            $headerPayload['total'] = $state->total;
            $headerPayload['rounding_adjustment'] = $state->rounding_adjustment;
        }

        PurchaseOrder::withTrashed()
            ->whereKey($state->purchase_order_id)
            ->update($headerPayload);

        foreach ($this->line_updates as $lineUpdate) {
            $itemId = (int) $lineUpdate['purchase_order_item_id'];
            $existing = PurchaseOrderItem::query()->find($itemId);

            if ($existing === null) {
                continue;
            }

            $payload = [
                'received_quantity' => (string) $lineUpdate['received_quantity'],
            ];

            if ($this->tax_finalised) {
                $payload['tax'] = (int) $lineUpdate['tax'];
                $payload['total'] = (int) $lineUpdate['total'];
            }

            PurchaseOrderItem::query()
                ->whereKey($itemId)
                ->update($payload);
        }
    }
}
