<?php

declare(strict_types=1);

namespace App\Domain\VirtualStock\Verbs\Events;

use App\Domain\Availability\Services\VirtualStockDemandResolver;
use App\Domain\VirtualStock\Models\VirtualStockIntake;
use App\Domain\VirtualStock\Services\VirtualStockStatusRegistry;
use App\Domain\VirtualStock\Verbs\States\VirtualStockIntakeState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

/**
 * Cancels a virtual stock intake and voids any active supply demand.
 */
class VirtualStockIntakeCancelled extends Event
{
    public function __construct(
        #[StateId(VirtualStockIntakeState::class)]
        public int $state_id,
        public string $status = 'cancelled',
        public ?string $cancelled_at = null,
        public ?string $reason = null,
    ) {}

    public function validate(VirtualStockIntakeState $state): void
    {
        $this->assert(
            ! $state->is_deleted,
            'Deleted virtual stock intakes cannot be cancelled.',
        );

        app(VirtualStockStatusRegistry::class)->assertCanTransitionTo(
            $state->status,
            $this->status,
        );
    }

    public function apply(VirtualStockIntakeState $state): void
    {
        $state->status = $this->status;
        $state->cancelled_at = $this->cancelled_at !== null
            ? CarbonImmutable::parse($this->cancelled_at)
            : null;
        $state->last_event_at = $state->cancelled_at;
    }

    public function handle(VirtualStockIntakeState $state): void
    {
        $intake = VirtualStockIntake::query()->whereKey($state->virtual_stock_intake_id)->first();
        $notes = $intake?->notes;

        if ($this->reason !== null && $this->reason !== '') {
            $notes = trim(($notes ?? '').($notes ? "\n\n" : '').'Cancellation: '.$this->reason);
        }

        VirtualStockIntake::query()
            ->whereKey($state->virtual_stock_intake_id)
            ->update([
                'status' => $state->status,
                'cancelled_at' => $state->cancelled_at,
                'notes' => $notes,
                'updated_at' => $state->last_event_at,
            ]);

        if ($intake !== null) {
            app(VirtualStockDemandResolver::class)->releaseDemands($intake->refresh());
        }
    }
}
