<?php

namespace App\Verbs\Events\VirtualStock;

use App\Models\Demand;
use App\Models\VirtualStockIntake;
use App\Services\Availability\VirtualStockDemandResolver;
use App\Services\VirtualStock\VirtualStockAssetProvisioner;
use App\Services\VirtualStock\VirtualStockStatusRegistry;
use App\Verbs\States\VirtualStockIntakeState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

/**
 * Marks a confirmed virtual stock intake as physically received.
 */
class VirtualStockIntakeReceived extends Event
{
    public function __construct(
        #[StateId(VirtualStockIntakeState::class)]
        public int $state_id,
        public string $status = 'received',
        public ?string $received_at = null,
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
        $state->received_at = $this->received_at !== null
            ? CarbonImmutable::parse($this->received_at)
            : null;
        $state->last_event_at = $state->received_at;
    }

    public function handle(VirtualStockIntakeState $state): void
    {
        VirtualStockIntake::query()
            ->whereKey($state->virtual_stock_intake_id)
            ->update([
                'status' => $state->status,
                'received_at' => $state->received_at,
                'updated_at' => $state->last_event_at,
            ]);

        $intake = VirtualStockIntake::query()->find($state->virtual_stock_intake_id);

        if ($intake !== null) {
            $resolver = app(VirtualStockDemandResolver::class);

            if ($state->demand_id !== null && $state->demand_id > 0) {
                $resolver->syncSupplyDemand($intake, $state->demand_id);
            }

            if ($state->serialised_assets_provisioned) {
                $this->resyncSerialisedAssetSupply($intake);
            }
        }
    }

    private function resyncSerialisedAssetSupply(VirtualStockIntake $intake): void
    {
        $provisioner = app(VirtualStockAssetProvisioner::class);

        Demand::query()
            ->where('source_type', 'virtual_stock_intake')
            ->where('source_id', $intake->id)
            ->whereNotNull('asset_id')
            ->orderBy('id')
            ->each(function (Demand $demand) use ($intake, $provisioner): void {
                if ($demand->asset_id === null) {
                    return;
                }

                $provisioner->syncSupplyDemand(
                    $intake,
                    (int) $demand->asset_id,
                    (int) $demand->id,
                );
            });
    }
}
