<?php

declare(strict_types=1);

namespace App\Domain\VirtualStock\Actions;

use App\Concerns\CommitsVerbsEvents;
use App\Domain\VirtualStock\Data\VirtualStockIntakeData;
use App\Domain\VirtualStock\Models\VirtualStockIntake;
use App\Domain\VirtualStock\Services\SerialisedVirtualStockAssetCoordinator;
use App\Domain\VirtualStock\Services\VirtualStockIntakeEventRecorder;
use App\Domain\VirtualStock\Services\VirtualStockStatusRegistry;
use App\Domain\VirtualStock\Verbs\Events\VirtualStockIntakeReceived;
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
            $this->lockModelForMutation(
                $intake,
                assert: fn (VirtualStockIntake $locked) => app(VirtualStockStatusRegistry::class)
                    ->assertCanTransitionTo($locked->status, $status),
            );

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
