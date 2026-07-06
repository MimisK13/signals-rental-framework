<?php

namespace App\Verbs\Events\PurchaseOrders;

use App\Models\PurchaseOrder;
use App\Verbs\States\PurchaseOrderState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

/**
 * Soft-deletes a draft purchase order projection. Replay-safe via withTrashed().
 */
class PurchaseOrderDeleted extends Event
{
    public function __construct(
        #[StateId(PurchaseOrderState::class)]
        public int $state_id,
        public ?string $deleted_at = null,
        public ?int $deleted_by = null,
    ) {}

    public function validate(PurchaseOrderState $state): void
    {
        $this->assert(
            ! $state->is_deleted,
            'This purchase order is already deleted.',
        );

        $this->assert(
            $state->status === 'draft',
            'Only draft purchase orders can be deleted.',
        );
    }

    public function apply(PurchaseOrderState $state): void
    {
        $state->is_deleted = true;
        $state->deleted_at = $this->deleted_at !== null
            ? CarbonImmutable::parse($this->deleted_at)
            : null;
        $state->last_event_at = $state->deleted_at ?? $state->last_event_at;
    }

    public function handle(PurchaseOrderState $state): void
    {
        $deletedAt = $this->deleted_at !== null
            ? CarbonImmutable::parse($this->deleted_at)
            : CarbonImmutable::now('UTC');

        $purchaseOrder = PurchaseOrder::withTrashed()->whereKey($state->purchase_order_id)->first();

        if ($purchaseOrder === null || $purchaseOrder->trashed()) {
            return;
        }

        PurchaseOrder::withTrashed()
            ->whereKey($state->purchase_order_id)
            ->update([
                'deleted_at' => $deletedAt,
                'updated_at' => $deletedAt,
            ]);
    }
}
