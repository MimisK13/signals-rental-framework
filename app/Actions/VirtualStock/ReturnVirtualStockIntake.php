<?php

namespace App\Actions\VirtualStock;

use App\Concerns\CommitsVerbsEvents;
use App\Data\VirtualStock\VirtualStockIntakeData;
use App\Models\VirtualStockIntake;
use App\Services\VirtualStock\VirtualStockStatusRegistry;
use App\Verbs\Events\VirtualStock\VirtualStockIntakeReturned;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;

class ReturnVirtualStockIntake
{
    use CommitsVerbsEvents;

    public function __construct(
        private readonly ApportionVirtualStockCost $apportionVirtualStockCost,
    ) {}

    public function __invoke(VirtualStockIntake $intake): VirtualStockIntakeData
    {
        Gate::authorize('return', $intake);

        $status = 'returned';
        app(VirtualStockStatusRegistry::class)->assertKnown($status);
        app(VirtualStockStatusRegistry::class)->assertCanTransitionTo($intake->status, $status);

        $returnedAt = CarbonImmutable::now('UTC');

        $this->commitVerbs(function () use ($intake, $status, $returnedAt): void {
            VirtualStockIntakeReturned::fire(
                state_id: (int) $intake->state_id,
                status: $status,
                returned_at: $returnedAt->toIso8601String(),
            );
        });

        ($this->apportionVirtualStockCost)->removeForIntake($intake->refresh(), authorize: false);

        return VirtualStockIntakeData::fromModel($intake->refresh());
    }
}
