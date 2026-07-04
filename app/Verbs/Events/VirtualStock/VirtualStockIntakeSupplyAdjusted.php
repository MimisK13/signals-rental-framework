<?php

namespace App\Verbs\Events\VirtualStock;

use App\Models\VirtualStockIntake;
use App\Services\Availability\VirtualStockDemandResolver;
use App\Verbs\States\VirtualStockIntakeState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

/**
 * Revises the supply quantity on a confirmed intake when a later PO receipt
 * increases the cumulative received amount (partial receipt follow-up).
 */
class VirtualStockIntakeSupplyAdjusted extends Event
{
    public function __construct(
        #[StateId(VirtualStockIntakeState::class)]
        public int $state_id,
        public int $quantity = 0,
        public ?string $adjusted_at = null,
    ) {}

    public function validate(VirtualStockIntakeState $state): void
    {
        $this->assert(
            ! $state->is_deleted,
            'Deleted virtual stock intakes cannot be adjusted.',
        );

        $this->assert(
            in_array($state->status, ['confirmed', 'received'], true),
            'Only confirmed virtual stock intakes can have supply adjusted.',
        );
    }

    public function apply(VirtualStockIntakeState $state): void
    {
        $state->quantity = $this->quantity;
        $state->last_event_at = $this->adjusted_at !== null
            ? CarbonImmutable::parse($this->adjusted_at)
            : $state->last_event_at;
    }

    public function handle(VirtualStockIntakeState $state): void
    {
        VirtualStockIntake::withTrashed()
            ->whereKey($state->virtual_stock_intake_id)
            ->update([
                'quantity' => $state->quantity,
                'updated_at' => $state->last_event_at,
            ]);

        $intake = VirtualStockIntake::query()->find($state->virtual_stock_intake_id);

        if ($intake !== null && $state->demand_id !== null) {
            app(VirtualStockDemandResolver::class)
                ->syncSupplyDemand($intake->refresh(), $state->demand_id);
        }
    }
}
