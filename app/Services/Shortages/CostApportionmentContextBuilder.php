<?php

namespace App\Services\Shortages;

use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\ShortageResolution;
use App\Models\VirtualStockIntake;
use Illuminate\Support\Carbon;

class CostApportionmentContextBuilder
{
    public function build(VirtualStockIntake $intake): CostApportionmentContext
    {
        $intake->loadMissing(['purchaseOrder']);

        /** @var array<int, int> $quantities */
        $quantities = [];
        /** @var array<int, array{from: Carbon, until: Carbon}> $rentalWindows */
        $rentalWindows = [];
        $primaryOpportunityId = $intake->primary_opportunity_id;

        $resolution = ShortageResolution::query()
            ->where('virtual_stock_intake_id', $intake->id)
            ->with(['items.opportunityItem'])
            ->first();

        if ($resolution !== null) {
            $metadata = $resolution->metadata ?? [];

            if ($primaryOpportunityId === null && isset($metadata['opportunity_id'])) {
                $primaryOpportunityId = (int) $metadata['opportunity_id'];
            }

            foreach ($resolution->items as $item) {
                $opportunityItem = $item->opportunityItem;
                $opportunityId = (int) $opportunityItem->opportunity_id;
                $quantities[$opportunityId] = ($quantities[$opportunityId] ?? 0) + (int) $item->quantity_allocated;
                $this->accumulateRentalWindow($rentalWindows, $opportunityId, $opportunityItem);
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
            rentalWindowsByOpportunity: $rentalWindows,
        );
    }

    /**
     * @param  array<int, array{from: Carbon, until: Carbon}>  $rentalWindows
     */
    private function accumulateRentalWindow(array &$rentalWindows, int $opportunityId, OpportunityItem $item): void
    {
        if ($item->starts_at === null || $item->ends_at === null) {
            return;
        }

        $from = Carbon::parse($item->starts_at)->utc();
        $until = Carbon::parse($item->ends_at)->utc();

        if (! isset($rentalWindows[$opportunityId])) {
            $rentalWindows[$opportunityId] = [
                'from' => $from,
                'until' => $until,
            ];

            return;
        }

        if ($from->lessThan($rentalWindows[$opportunityId]['from'])) {
            $rentalWindows[$opportunityId]['from'] = $from;
        }

        if ($until->greaterThan($rentalWindows[$opportunityId]['until'])) {
            $rentalWindows[$opportunityId]['until'] = $until;
        }
    }
}
