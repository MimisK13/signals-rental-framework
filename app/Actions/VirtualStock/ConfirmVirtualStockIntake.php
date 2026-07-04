<?php

namespace App\Actions\VirtualStock;

use App\Concerns\CommitsVerbsEvents;
use App\Data\VirtualStock\VirtualStockIntakeData;
use App\Models\VirtualStockIntake;
use App\Services\SequenceAllocator;
use App\Services\VirtualStock\VirtualStockIntakeEventRecorder;
use App\Services\VirtualStock\VirtualStockStatusRegistry;
use App\Verbs\Events\VirtualStock\VirtualStockIntakeConfirmed;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;

class ConfirmVirtualStockIntake
{
    use CommitsVerbsEvents;

    public function __construct(
        private readonly ApportionVirtualStockCost $apportionVirtualStockCost,
    ) {}

    public function __invoke(VirtualStockIntake $intake): VirtualStockIntakeData
    {
        Gate::authorize('confirm', $intake);

        $status = 'confirmed';
        app(VirtualStockStatusRegistry::class)->assertKnown($status);
        app(VirtualStockStatusRegistry::class)->assertCanTransitionTo($intake->status, $status);

        $confirmedAt = CarbonImmutable::now('UTC');
        $demandId = app(SequenceAllocator::class)->next('demands');

        $this->commitVerbs(function () use ($intake, $status, $confirmedAt, $demandId): void {
            VirtualStockIntakeConfirmed::fire(
                state_id: (int) $intake->state_id,
                status: $status,
                demand_id: $demandId,
                confirmed_at: $confirmedAt->toIso8601String(),
            );
        });

        ($this->apportionVirtualStockCost)($intake->refresh(), authorize: false);

        $intake = $intake->refresh();
        app(VirtualStockIntakeEventRecorder::class)->confirmed($intake);

        return VirtualStockIntakeData::fromModel($intake);
    }
}
