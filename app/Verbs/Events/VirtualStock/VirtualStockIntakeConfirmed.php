<?php

namespace App\Verbs\Events\VirtualStock;

use App\Models\VirtualStockIntake;
use App\Services\Availability\VirtualStockDemandResolver;
use App\Services\VirtualStock\VirtualStockStatusRegistry;
use App\Verbs\States\VirtualStockIntakeState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

/**
 * Confirms a virtual stock intake and projects the negative supply demand row.
 */
class VirtualStockIntakeConfirmed extends Event
{
    public function __construct(
        #[StateId(VirtualStockIntakeState::class)]
        public int $state_id,
        public string $status = 'confirmed',
        public int $demand_id = 0,
        public ?string $confirmed_at = null,
    ) {}

    public function validate(VirtualStockIntakeState $state): void
    {
        $this->assert(
            ! $state->is_deleted,
            'Deleted virtual stock intakes cannot be confirmed.',
        );

        app(VirtualStockStatusRegistry::class)->assertCanTransitionTo(
            $state->status,
            $this->status,
        );
    }

    public function apply(VirtualStockIntakeState $state): void
    {
        $state->status = $this->status;
        $state->demand_id = $this->demand_id;
        $state->confirmed_at = $this->confirmed_at !== null
            ? CarbonImmutable::parse($this->confirmed_at)
            : null;
        $state->last_event_at = $state->confirmed_at;
    }

    public function handle(VirtualStockIntakeState $state): void
    {
        $intake = VirtualStockIntake::query()->find($state->virtual_stock_intake_id);

        if ($intake === null) {
            return;
        }

        VirtualStockIntake::query()
            ->whereKey($state->virtual_stock_intake_id)
            ->update([
                'status' => $state->status,
                'confirmed_at' => $state->confirmed_at,
            ]);

        $intake->refresh();

        app(VirtualStockDemandResolver::class)->syncSupplyDemand($intake, $this->demand_id);

        VirtualStockIntake::withTrashed()
            ->whereKey($state->virtual_stock_intake_id)
            ->update([
                'demand_id' => $state->demand_id,
                'updated_at' => $state->last_event_at,
            ]);
    }
}
