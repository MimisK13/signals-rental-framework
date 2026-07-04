<?php

namespace App\Verbs\Events\VirtualStock;

use App\Models\VirtualStockIntake;
use App\Verbs\States\VirtualStockIntakeState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

/**
 * Soft-deletes a draft virtual stock intake projection.
 */
class VirtualStockIntakeDeleted extends Event
{
    public function __construct(
        #[StateId(VirtualStockIntakeState::class)]
        public int $state_id,
        public ?string $deleted_at = null,
        public ?int $deleted_by = null,
    ) {}

    public function validate(VirtualStockIntakeState $state): void
    {
        $this->assert(
            ! $state->is_deleted,
            'This virtual stock intake is already deleted.',
        );

        $this->assert(
            $state->status === 'draft',
            'Only draft virtual stock intakes can be deleted.',
        );
    }

    public function apply(VirtualStockIntakeState $state): void
    {
        $state->is_deleted = true;
        $state->deleted_at = $this->deleted_at !== null
            ? CarbonImmutable::parse($this->deleted_at)
            : null;
        $state->last_event_at = $state->deleted_at ?? $state->last_event_at;
    }

    public function handle(VirtualStockIntakeState $state): void
    {
        $deletedAt = $this->deleted_at !== null
            ? CarbonImmutable::parse($this->deleted_at)
            : CarbonImmutable::now('UTC');

        $intake = VirtualStockIntake::withTrashed()->whereKey($state->virtual_stock_intake_id)->first();

        if ($intake === null || $intake->trashed()) {
            return;
        }

        VirtualStockIntake::withTrashed()
            ->whereKey($state->virtual_stock_intake_id)
            ->update([
                'deleted_at' => $deletedAt,
                'updated_at' => $deletedAt,
            ]);
    }
}
