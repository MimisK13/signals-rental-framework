<?php

namespace App\Verbs\Events\PurchaseOrders;

use App\Models\PurchaseOrder;
use App\Services\PurchaseOrders\PurchaseOrderStatusRegistry;
use App\Verbs\States\PurchaseOrderState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

/**
 * Cancels a purchase order that has not been received.
 */
class PurchaseOrderCancelled extends Event
{
    public function __construct(
        #[StateId(PurchaseOrderState::class)]
        public int $state_id,
        public string $status = 'cancelled',
        public ?string $cancelled_at = null,
        public ?string $reason = null,
    ) {}

    public function validate(PurchaseOrderState $state): void
    {
        $this->assert(
            ! $state->is_deleted,
            'Deleted purchase orders cannot be cancelled.',
        );

        app(PurchaseOrderStatusRegistry::class)->assertCanTransitionTo(
            $state->status,
            $this->status,
        );
    }

    public function apply(PurchaseOrderState $state): void
    {
        $state->status = $this->status;
        $state->cancelled_at = $this->cancelled_at !== null
            ? CarbonImmutable::parse($this->cancelled_at)
            : null;
        $state->last_event_at = $state->cancelled_at;
    }

    public function handle(PurchaseOrderState $state): void
    {
        $purchaseOrder = PurchaseOrder::query()->whereKey($state->purchase_order_id)->first();
        $notes = $purchaseOrder?->notes;

        if ($this->reason !== null && $this->reason !== '') {
            $notes = trim(($notes ?? '').($notes ? "\n\n" : '').'Cancellation: '.$this->reason);
        }

        PurchaseOrder::query()
            ->whereKey($state->purchase_order_id)
            ->update([
                'status' => $state->status,
                'cancelled_at' => $state->cancelled_at,
                'notes' => $notes,
                'updated_at' => $state->cancelled_at,
            ]);
    }
}
