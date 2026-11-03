<?php

declare(strict_types=1);

namespace App\Domain\VirtualStock\Actions;

use App\Domain\VirtualStock\Data\ApportionVirtualStockCostResult;
use App\Domain\Projects\Opportunities\Models\Opportunity;
use App\Domain\VirtualStock\Models\VirtualStockCostOverride;
use App\Domain\VirtualStock\Models\VirtualStockIntake;
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
