<?php

namespace App\Verbs\Events\VirtualStock;

use App\Models\VirtualStockIntake;
use App\Services\VirtualStock\VirtualStockAssetRetirer;
use App\Verbs\States\VirtualStockIntakeState;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

/**
 * Retires all serialised virtual assets for an intake and voids supply demands.
 */
class VirtualStockIntakeSerialisedAssetsRetired extends Event
{
    public function __construct(
        #[StateId(VirtualStockIntakeState::class)]
        public int $state_id,
        public ?string $retired_at = null,
    ) {}

    public function validate(VirtualStockIntakeState $state): void
    {
        $this->assert(
            $state->serialised_assets_provisioned,
            'No serialised virtual assets exist to retire for this intake.',
        );

        $this->assert(
            ! $state->serialised_assets_retired,
            'Serialised virtual assets have already been retired for this intake.',
        );
    }

    public function apply(VirtualStockIntakeState $state): void
    {
        $state->serialised_assets_retired = true;
        $state->last_event_at = $this->retired_at !== null
            ? CarbonImmutable::parse($this->retired_at)
            : CarbonImmutable::now('UTC');
    }

    public function handle(VirtualStockIntakeState $state): void
    {
        $intake = VirtualStockIntake::query()->find($state->virtual_stock_intake_id);

        if ($intake === null) {
            return;
        }

        $retiredAt = $this->retired_at !== null
            ? Carbon::parse($this->retired_at)
            : Carbon::now('UTC');

        app(VirtualStockAssetRetirer::class)->retireForIntake($intake, $retiredAt);

        VirtualStockIntake::query()
            ->whereKey($state->virtual_stock_intake_id)
            ->update(['updated_at' => $state->last_event_at]);
    }
}
