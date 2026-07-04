<?php

namespace App\Actions\VirtualStock;

use App\Data\VirtualStock\ApportionVirtualStockCostResult;
use App\Models\Opportunity;
use App\Models\VirtualStockCostOverride;
use App\Models\VirtualStockIntake;
use Illuminate\Support\Facades\Gate;

class ClearVirtualStockCostOverride
{
    public function __construct(
        private readonly ApportionVirtualStockCost $apportionVirtualStockCost,
    ) {}

    public function __invoke(
        VirtualStockIntake $intake,
        Opportunity $opportunity,
    ): ApportionVirtualStockCostResult {
        Gate::authorize('costs.view');
        Gate::authorize('manageCostOverrides', $intake);

        VirtualStockCostOverride::query()
            ->where('virtual_stock_intake_id', $intake->id)
            ->where('opportunity_id', $opportunity->id)
            ->delete();

        return ($this->apportionVirtualStockCost)($intake->refresh(), authorize: false);
    }
}
