<?php

namespace App\Verbs\Events\VirtualStock;

use App\Models\VirtualStockIntake;
use App\Verbs\States\VirtualStockIntakeState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

/**
 * Full re-snapshot update for draft virtual stock intakes.
 */
class VirtualStockIntakeUpdated extends Event
{
    public function __construct(
        #[StateId(VirtualStockIntakeState::class)]
        public int $state_id,
        public ?int $purchase_order_id = null,
        public ?int $purchase_order_item_id = null,
        public int $product_id = 0,
        public int $store_id = 0,
        public int $quantity = 0,
        public ?string $available_from = null,
        public ?string $available_until = null,
        public int $unit_cost = 0,
        public ?string $currency_code = null,
        public ?string $notes = null,
        public ?string $updated_at = null,
    ) {}

    public function validate(VirtualStockIntakeState $state): void
    {
        $this->assert(
            $state->status === 'draft' && ! $state->is_deleted,
            'Only draft virtual stock intakes can be edited.',
        );
    }

    public function apply(VirtualStockIntakeState $state): void
    {
        $state->purchase_order_id = $this->purchase_order_id;
        $state->purchase_order_item_id = $this->purchase_order_item_id;
        $state->product_id = $this->product_id;
        $state->store_id = $this->store_id;
        $state->quantity = $this->quantity;
        $state->available_from = $this->available_from !== null
            ? CarbonImmutable::parse($this->available_from)
            : null;
        $state->available_until = $this->available_until !== null
            ? CarbonImmutable::parse($this->available_until)
            : null;
        $state->unit_cost = $this->unit_cost;
        $state->currency_code = $this->currency_code ?? $state->currency_code;
        $state->notes = $this->notes;
        $state->last_event_at = $this->updated_at !== null
            ? CarbonImmutable::parse($this->updated_at)
            : $state->last_event_at;
    }

    public function handle(VirtualStockIntakeState $state): void
    {
        VirtualStockIntake::withTrashed()
            ->whereKey($state->virtual_stock_intake_id)
            ->update([
                'purchase_order_id' => $state->purchase_order_id,
                'purchase_order_item_id' => $state->purchase_order_item_id,
                'product_id' => $state->product_id,
                'store_id' => $state->store_id,
                'quantity' => $state->quantity,
                'available_from' => $state->available_from,
                'available_until' => $state->available_until,
                'unit_cost' => $state->unit_cost,
                'currency_code' => $state->currency_code,
                'notes' => $state->notes,
                'updated_at' => $state->last_event_at,
            ]);
    }
}
