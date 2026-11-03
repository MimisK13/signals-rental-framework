<?php

declare(strict_types=1);

namespace App\Domain\VirtualStock\Actions;

use App\Concerns\CommitsVerbsEvents;
use App\Domain\VirtualStock\Data\VirtualStockIntakeData;
use App\Domain\VirtualStock\Models\VirtualStockIntake;
use App\Domain\VirtualStock\Services\SerialisedVirtualStockAssetCoordinator;
use App\Domain\VirtualStock\Services\VirtualStockIntakeEventRecorder;
use App\Domain\VirtualStock\Services\VirtualStockStatusRegistry;
use App\Domain\VirtualStock\Verbs\Events\VirtualStockIntakeCancelled;
use App\Domain\VirtualStock\Verbs\Events\VirtualStockIntakeSerialisedAssetsRetired;
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
            $this->lockModelForMutation(
                $intake,
                assert: fn (VirtualStockIntake $locked) => app(VirtualStockStatusRegistry::class)
                    ->assertCanTransitionTo($locked->status, $status),
            );

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
