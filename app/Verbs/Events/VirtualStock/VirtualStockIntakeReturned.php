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
 * Marks a received virtual stock intake as returned and voids supply demand.
 */
class VirtualStockIntakeReturned extends Event
{
    public function __construct(
        #[StateId(VirtualStockIntakeState::class)]
        public int $state_id,
        public string $status = 'returned',
        public ?string $returned_at = null,
    ) {}

    public function validate(VirtualStockIntakeState $state): void
    {
        app(VirtualStockStatusRegistry::class)->assertCanTransitionTo(
            $state->status,
            $this->status,
        );
    }

    public function apply(VirtualStockIntakeState $state): void
    {
        $state->status = $this->status;
        $state->returned_at = $this->returned_at !== null
            ? CarbonImmutable::parse($this->returned_at)
            : null;
        $state->last_event_at = $state->returned_at;
    }

    public function handle(VirtualStockIntakeState $state): void
    {
        VirtualStockIntake::query()
            ->whereKey($state->virtual_stock_intake_id)
            ->update([
                'status' => $state->status,
                'returned_at' => $state->returned_at,
                'updated_at' => $state->last_event_at,
            ]);

        $intake = VirtualStockIntake::query()->find($state->virtual_stock_intake_id);

        if ($intake !== null) {
            app(VirtualStockDemandResolver::class)->releaseDemands($intake);
        }
    }
}
