<?php

namespace App\Actions\VirtualStock;

use App\Concerns\CommitsVerbsEvents;
use App\Data\VirtualStock\ReturnVirtualStockIntakeData;
use App\Data\VirtualStock\VirtualStockIntakeData;
use App\Models\VirtualStockIntake;
use App\Services\VirtualStock\SerialisedVirtualStockAssetCoordinator;
use App\Services\VirtualStock\VirtualStockIntakeEventRecorder;
use App\Services\VirtualStock\VirtualStockIntakeReturnGuard;
use App\Services\VirtualStock\VirtualStockStatusRegistry;
use App\Verbs\Events\VirtualStock\VirtualStockIntakeReturned;
use App\Verbs\Events\VirtualStock\VirtualStockIntakeSerialisedAssetsRetired;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ReturnVirtualStockIntake
{
    use CommitsVerbsEvents;

    public function __construct(
        private readonly ApportionVirtualStockCost $apportionVirtualStockCost,
        private readonly VirtualStockIntakeReturnGuard $returnGuard,
    ) {}

    public function __invoke(
        VirtualStockIntake $intake,
        ?ReturnVirtualStockIntakeData $data = null,
    ): VirtualStockIntakeData {
        Gate::authorize('return', $intake);

        $data ??= new ReturnVirtualStockIntakeData;

        if (! $data->force && $this->returnGuard->hasOutstandingAllocations($intake)) {
            throw ValidationException::withMessages([
                'force' => ['One or more linked opportunities still have quantity out on hire. Confirm a forced return to proceed.'],
                'outstanding_allocations' => $this->returnGuard->outstandingAllocations($intake),
            ]);
        }

        $status = 'returned';
        app(VirtualStockStatusRegistry::class)->assertKnown($status);
        app(VirtualStockStatusRegistry::class)->assertCanTransitionTo($intake->status, $status);

        $returnedAt = CarbonImmutable::now('UTC');
        $coordinator = app(SerialisedVirtualStockAssetCoordinator::class);

        $this->commitVerbs(function () use ($intake, $status, $returnedAt, $coordinator): void {
            $this->lockModelForMutation(
                $intake,
                assert: fn (VirtualStockIntake $locked) => app(VirtualStockStatusRegistry::class)
                    ->assertCanTransitionTo($locked->status, $status),
            );

            VirtualStockIntakeReturned::fire(
                state_id: (int) $intake->state_id,
                status: $status,
                returned_at: $returnedAt->toIso8601String(),
            );

            if ($coordinator->shouldRetireAssets($intake)) {
                VirtualStockIntakeSerialisedAssetsRetired::fire(
                    state_id: (int) $intake->state_id,
                    retired_at: $returnedAt->toIso8601String(),
                );
            }
        });

        ($this->apportionVirtualStockCost)->removeForIntake($intake->refresh(), authorize: false);

        $intake = $intake->refresh();
        app(VirtualStockIntakeEventRecorder::class)->returned($intake);

        return VirtualStockIntakeData::fromModel($intake);
    }
}
