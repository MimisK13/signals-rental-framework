<?php

namespace App\Actions\VirtualStock;

use App\Concerns\CommitsVerbsEvents;
use App\Data\VirtualStock\VirtualStockIntakeData;
use App\Models\VirtualStockIntake;
use App\Services\VirtualStock\SerialisedVirtualStockAssetCoordinator;
use App\Services\VirtualStock\VirtualStockIntakeEventRecorder;
use App\Services\VirtualStock\VirtualStockStatusRegistry;
use App\Verbs\Events\VirtualStock\VirtualStockIntakeCancelled;
use App\Verbs\Events\VirtualStock\VirtualStockIntakeSerialisedAssetsRetired;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;

class CancelVirtualStockIntake
{
    use CommitsVerbsEvents;

    public function __construct(
        private readonly ApportionVirtualStockCost $apportionVirtualStockCost,
    ) {}

    public function __invoke(VirtualStockIntake $intake, ?string $reason = null): VirtualStockIntakeData
    {
        Gate::authorize('cancel', $intake);

        $status = 'cancelled';
        app(VirtualStockStatusRegistry::class)->assertKnown($status);
        app(VirtualStockStatusRegistry::class)->assertCanTransitionTo($intake->status, $status);

        $cancelledAt = CarbonImmutable::now('UTC');
        $coordinator = app(SerialisedVirtualStockAssetCoordinator::class);

        $this->commitVerbs(function () use ($intake, $status, $cancelledAt, $reason, $coordinator): void {
            VirtualStockIntakeCancelled::fire(
                state_id: (int) $intake->state_id,
                status: $status,
                cancelled_at: $cancelledAt->toIso8601String(),
                reason: $reason,
            );

            if ($coordinator->shouldRetireAssets($intake)) {
                VirtualStockIntakeSerialisedAssetsRetired::fire(
                    state_id: (int) $intake->state_id,
                    retired_at: $cancelledAt->toIso8601String(),
                );
            }
        });

        ($this->apportionVirtualStockCost)->removeForIntake($intake->refresh(), authorize: false);

        $intake = $intake->refresh();
        app(VirtualStockIntakeEventRecorder::class)->cancelled($intake, $reason);

        return VirtualStockIntakeData::fromModel($intake);
    }
}
