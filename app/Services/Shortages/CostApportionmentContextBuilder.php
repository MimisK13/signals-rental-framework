<?php

namespace App\Services\Shortages;

use App\Models\Opportunity;
use App\Models\ShortageResolution;
use App\Models\VirtualStockIntake;

class CostApportionmentContextBuilder
{
    public function build(VirtualStockIntake $intake): CostApportionmentContext
    {
        $intake->loadMissing(['purchaseOrder']);

        /** @var array<int, int> $quantities */
        $quantities = [];
        $primaryOpportunityId = null;

        $resolution = ShortageResolution::query()
            ->where('virtual_stock_intake_id', $intake->id)
            ->with(['items.opportunityItem'])
            ->first();

        if ($resolution !== null) {
            $metadata = $resolution->metadata ?? [];
            $primaryOpportunityId = isset($metadata['opportunity_id'])
                ? (int) $metadata['opportunity_id']
                : null;

            foreach ($resolution->items as $item) {
                $opportunityId = (int) $item->opportunityItem->opportunity_id;
                $quantities[$opportunityId] = ($quantities[$opportunityId] ?? 0) + (int) $item->quantity_allocated;
            }
        }

        if ($quantities === [] && $intake->purchaseOrder?->opportunity_id !== null) {
            $opportunityId = (int) $intake->purchaseOrder->opportunity_id;
            $quantities[$opportunityId] = (int) $intake->quantity;
            $primaryOpportunityId ??= $opportunityId;
        }

        ksort($quantities);

        $opportunityIds = array_keys($quantities);
        $opportunities = $opportunityIds === []
            ? []
            : Opportunity::query()->whereIn('id', $opportunityIds)->get()->keyBy('id')->all();

        if ($primaryOpportunityId === null && $quantities !== []) {
            $primaryOpportunityId = array_key_first($quantities);
        }

        $totalCostMinor = (int) $intake->unit_cost * (int) $intake->quantity;

        return new CostApportionmentContext(
            intake: $intake,
            quantitiesByOpportunity: $quantities,
            opportunities: $opportunities,
            primaryOpportunityId: $primaryOpportunityId,
            totalCostMinor: $totalCostMinor,
        );
    }
}
