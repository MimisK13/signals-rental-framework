<?php

namespace App\Verbs\Events\PurchaseOrders;

use App\Models\PurchaseOrder;
use App\Verbs\States\PurchaseOrderState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

/**
 * Sends a draft purchase order to the supplier.
 */
class PurchaseOrderSent extends Event
{
    public function __construct(
        #[StateId(PurchaseOrderState::class)]
        public int $state_id,
        public string $status = 'sent',
        public ?string $sent_at = null,
        public ?string $supplier_reference = null,
        public bool $supplier_reference_provided = false,
    ) {}

    public function validate(PurchaseOrderState $state): void
    {
        $this->assert(
            $state->status === 'draft' && ! $state->is_deleted,
            'Only draft purchase orders can be sent.',
        );
    }

    public function apply(PurchaseOrderState $state): void
    {
        $state->status = $this->status;
        $state->sent_at = $this->sent_at !== null
            ? CarbonImmutable::parse($this->sent_at)
            : null;

        if ($this->supplier_reference_provided) {
            $state->supplier_reference = $this->supplier_reference;
        }

        $state->last_event_at = $state->sent_at;
    }

    public function handle(PurchaseOrderState $state): void
    {
        $payload = [
            'status' => $state->status,
            'sent_at' => $state->sent_at,
            'updated_at' => $state->sent_at,
        ];

        if ($this->supplier_reference_provided) {
            $payload['supplier_reference'] = $state->supplier_reference;
        }

        PurchaseOrder::query()
            ->whereKey($state->purchase_order_id)
            ->update($payload);
    }
}
