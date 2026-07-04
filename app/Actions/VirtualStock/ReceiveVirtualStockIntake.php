<?php

namespace App\Actions\VirtualStock;

use App\Concerns\CommitsVerbsEvents;
use App\Data\VirtualStock\VirtualStockIntakeData;
use App\Models\VirtualStockIntake;
use App\Services\VirtualStock\SerialisedVirtualStockAssetCoordinator;
use App\Services\VirtualStock\VirtualStockIntakeEventRecorder;
use App\Services\VirtualStock\VirtualStockStatusRegistry;
use App\Verbs\Events\VirtualStock\VirtualStockIntakeReceived;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;

class ReceiveVirtualStockIntake
{
    use CommitsVerbsEvents;

    public function __construct(
        private readonly ApportionVirtualStockCost $apportionVirtualStockCost,
    ) {}

    public function __invoke(VirtualStockIntake $intake): VirtualStockIntakeData
    {
        Gate::authorize('receive', $intake);

        $status = 'received';
        app(VirtualStockStatusRegistry::class)->assertKnown($status);
        app(VirtualStockStatusRegistry::class)->assertCanTransitionTo($intake->status, $status);

        $receivedAt = CarbonImmutable::now('UTC');
        $coordinator = app(SerialisedVirtualStockAssetCoordinator::class);

        $this->commitVerbs(function () use ($intake, $status, $receivedAt, $coordinator): void {
            VirtualStockIntakeReceived::fire(
                state_id: (int) $intake->state_id,
                status: $status,
                received_at: $receivedAt->toIso8601String(),
            );

            if ($coordinator->needsProvisioning($intake)) {
                $coordinator->fireProvisionedEvent($intake, $receivedAt);
            }
        });

        ($this->apportionVirtualStockCost)($intake->refresh(), authorize: false);

        $intake = $intake->refresh();
        app(VirtualStockIntakeEventRecorder::class)->received($intake);

        return VirtualStockIntakeData::fromModel($intake);
    }
}
