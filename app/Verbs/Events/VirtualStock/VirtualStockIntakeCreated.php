<?php

namespace App\Verbs\Events\VirtualStock;

use App\Models\VirtualStockIntake;
use App\Verbs\States\VirtualStockIntakeState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

/**
 * Genesis event for a virtual stock intake.
 */
class VirtualStockIntakeCreated extends Event
{
    public function __construct(
        public int $virtual_stock_intake_id,
        #[StateId(VirtualStockIntakeState::class)]
        public ?int $state_id = null,
        public string $number = '',
        public string $status = 'draft',
        public ?int $purchase_order_id = null,
        public ?int $purchase_order_item_id = null,
        public ?int $primary_opportunity_id = null,
        public int $product_id = 0,
        public int $store_id = 0,
        public int $quantity = 0,
        public ?string $available_from = null,
        public ?string $available_until = null,
        public int $unit_cost = 0,
        public ?string $currency_code = null,
        public ?string $notes = null,
        public ?int $created_by = null,
        public ?string $created_at = null,
    ) {}

    public function apply(VirtualStockIntakeState $state): void
    {
        $state->virtual_stock_intake_id = $this->virtual_stock_intake_id;
        $state->number = $this->number;
        $state->status = $this->status;
        $state->purchase_order_id = $this->purchase_order_id;
        $state->purchase_order_item_id = $this->purchase_order_item_id;
        $state->primary_opportunity_id = $this->primary_opportunity_id;
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
        $state->currency_code = $this->currency_code ?? 'GBP';
        $state->notes = $this->notes;
        $state->created_by = $this->created_by;
        $state->created_at = $this->created_at !== null
            ? CarbonImmutable::parse($this->created_at)
            : null;
        $state->last_event_at = $state->created_at;
    }

    public function handle(VirtualStockIntakeState $state): void
    {
        VirtualStockIntake::withTrashed()->updateOrCreate(
            ['id' => $state->virtual_stock_intake_id],
            [
                'state_id' => $state->id,
                'number' => $state->number,
                'status' => $state->status,
                'purchase_order_id' => $state->purchase_order_id,
                'purchase_order_item_id' => $state->purchase_order_item_id,
                'primary_opportunity_id' => $state->primary_opportunity_id,
                'product_id' => $state->product_id,
                'store_id' => $state->store_id,
                'quantity' => $state->quantity,
                'available_from' => $state->available_from,
                'available_until' => $state->available_until,
                'unit_cost' => $state->unit_cost,
                'currency_code' => $state->currency_code,
                'notes' => $state->notes,
                'created_by' => $state->created_by,
                'demand_id' => $state->demand_id,
                'created_at' => $state->created_at,
                'updated_at' => $state->created_at,
            ],
        );
    }
}
