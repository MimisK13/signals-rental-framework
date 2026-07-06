<?php

namespace App\Actions\VirtualStock;

use App\Concerns\CommitsVerbsEvents;
use App\Data\VirtualStock\VirtualStockIntakeData;
use App\Enums\StockMethod;
use App\Models\Product;
use App\Models\VirtualStockIntake;
use App\Services\SequenceAllocator;
use App\Services\VirtualStock\SerialisedVirtualStockAssetCoordinator;
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
        $product = Product::query()->findOrFail($intake->product_id);
        $isSerialised = $product->stock_method === StockMethod::Serialised;
        $demandId = $isSerialised ? null : app(SequenceAllocator::class)->next('demands');
        $coordinator = app(SerialisedVirtualStockAssetCoordinator::class);

        $this->commitVerbs(function () use ($intake, $status, $confirmedAt, $demandId, $coordinator): void {
            $this->lockModelForMutation(
                $intake,
                assert: fn (VirtualStockIntake $locked) => app(VirtualStockStatusRegistry::class)
                    ->assertCanTransitionTo($locked->status, $status),
            );

            VirtualStockIntakeConfirmed::fire(
                state_id: (int) $intake->state_id,
                status: $status,
                demand_id: $demandId,
                confirmed_at: $confirmedAt->toIso8601String(),
            );

            $coordinator->fireProvisionedEvent($intake, $confirmedAt);
        });

        ($this->apportionVirtualStockCost)($intake->refresh(), authorize: false);

        $intake = $intake->refresh();
        app(VirtualStockIntakeEventRecorder::class)->confirmed($intake);

        return VirtualStockIntakeData::fromModel($intake);
    }
}
