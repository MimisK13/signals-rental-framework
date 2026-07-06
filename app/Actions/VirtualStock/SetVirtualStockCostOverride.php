<?php

namespace App\Actions\VirtualStock;

use App\Data\VirtualStock\ApportionVirtualStockCostResult;
use App\Data\VirtualStock\SetVirtualStockCostOverrideData;
use App\Models\VirtualStockCostOverride;
use App\Models\VirtualStockIntake;
use Illuminate\Support\Facades\Gate;

class SetVirtualStockCostOverride
{
    public function __construct(
        private readonly ApportionVirtualStockCost $apportionVirtualStockCost,
    ) {}

    public function __invoke(
        VirtualStockIntake $intake,
        SetVirtualStockCostOverrideData $data,
    ): ApportionVirtualStockCostResult {
        Gate::authorize('costs.view');
        Gate::authorize('manageCostOverrides', $intake);

        VirtualStockCostOverride::query()->updateOrCreate(
            [
                'virtual_stock_intake_id' => $intake->id,
                'opportunity_id' => $data->opportunity_id,
            ],
            [
                'unit_cost_override' => $data->unit_cost_override,
                'created_by' => auth()->id(),
            ],
        );

        return ($this->apportionVirtualStockCost)($intake->refresh(), authorize: false);
    }
}
