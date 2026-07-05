<?php

namespace App\Services\VirtualStock;

use App\Models\OpportunityCost;
use App\Models\OpportunityItem;
use App\Models\ShortageResolution;
use App\Models\VirtualStockIntake;
use App\Services\Shortages\CostApportionmentRegistry;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Builds per-opportunity P&L rows for a virtual stock intake by joining
 * apportioned supplier costs with charge-out revenue from linked shortage lines.
 */
class VirtualStockIntakeProfitAndLossBuilder
{
    public function __construct(
        private readonly CostApportionmentRegistry $strategyRegistry,
    ) {}

    /**
     * @return array{
     *     strategy_key: string,
     *     strategy_label: string,
     *     intake_total_minor: int,
     *     rows: list<array{
     *         opportunity_id: int,
     *         opportunity_subject: string,
     *         apportioned_cost_minor: int,
     *         revenue_minor: int,
     *         margin_minor: int,
     *         line_items: list<array{name: string, quantity_allocated: int, revenue_minor: int}>,
     *     }>,
     * }
     */
    public function build(VirtualStockIntake $intake, string $strategyKey, ?string $strategyLabel = null): array
    {
        $intake->loadMissing(['purchaseOrder']);

        $costs = OpportunityCost::query()
            ->where('virtual_stock_intake_id', $intake->id)
            ->with('opportunity')
            ->orderBy('opportunity_id')
            ->get()
            ->keyBy('opportunity_id');

        $revenueByOpportunity = $this->revenueByOpportunity($intake);
        $opportunityIds = array_values(array_unique(array_merge(
            $costs->keys()->all(),
            array_keys($revenueByOpportunity),
        )));
        sort($opportunityIds);

        $rows = [];

        foreach ($opportunityIds as $opportunityId) {
            $cost = $costs->get($opportunityId);
            $revenueMeta = $revenueByOpportunity[$opportunityId] ?? ['revenue_minor' => 0, 'line_items' => []];
            $apportionedMinor = $cost !== null ? (int) $cost->amount : 0;
            $revenueMinor = (int) $revenueMeta['revenue_minor'];
            $subject = $revenueMeta['opportunity_subject'] !== ''
                ? $revenueMeta['opportunity_subject']
                : 'Opportunity #'.$opportunityId;

            if ($cost !== null && $cost->opportunity !== null) {
                $subject = $cost->opportunity->subject;
            }

            $rows[] = [
                'opportunity_id' => $opportunityId,
                'opportunity_subject' => (string) $subject,
                'apportioned_cost_minor' => $apportionedMinor,
                'revenue_minor' => $revenueMinor,
                'margin_minor' => $revenueMinor - $apportionedMinor,
                'line_items' => $revenueMeta['line_items'],
            ];
        }

        return [
            'strategy_key' => $strategyKey,
            'strategy_label' => $strategyLabel ?? ($this->strategyRegistry->has($strategyKey)
                ? $this->strategyRegistry->get($strategyKey)->name()
                : $strategyKey),
            'intake_total_minor' => (int) $intake->unit_cost * (int) $intake->quantity,
            'rows' => $rows,
        ];
    }

    /**
     * @return array<int, array{
     *     revenue_minor: int,
     *     opportunity_subject: string,
     *     line_items: list<array{name: string, quantity_allocated: int, revenue_minor: int}>,
     * }>
     */
    private function revenueByOpportunity(VirtualStockIntake $intake): array
    {
        $resolution = ShortageResolution::query()
            ->where('virtual_stock_intake_id', $intake->id)
            ->with(['items.opportunityItem.opportunity'])
            ->first();

        if ($resolution === null) {
            if ($intake->purchaseOrder?->opportunity_id === null) {
                return [];
            }

            $opportunity = $intake->purchaseOrder->opportunity;

            return [
                (int) $intake->purchaseOrder->opportunity_id => [
                    'revenue_minor' => 0,
                    'opportunity_subject' => (string) ($opportunity !== null ? $opportunity->subject : ''),
                    'line_items' => [],
                ],
            ];
        }

        /** @var array<int, array{revenue_minor: int, opportunity_subject: string, line_items: list<array{name: string, quantity_allocated: int, revenue_minor: int}>}> $byOpportunity */
        $byOpportunity = [];

        foreach ($resolution->items as $pivot) {
            $item = $pivot->opportunityItem;
            $opportunityId = (int) $item->opportunity_id;
            $lineRevenue = $this->allocatedLineRevenueMinor($item, (int) $pivot->quantity_allocated);

            if (! array_key_exists($opportunityId, $byOpportunity)) {
                $byOpportunity[$opportunityId] = [
                    'revenue_minor' => 0,
                    'opportunity_subject' => (string) ($item->opportunity !== null ? $item->opportunity->subject : ''),
                    'line_items' => [],
                ];
            }

            $byOpportunity[$opportunityId]['revenue_minor'] += $lineRevenue;
            $byOpportunity[$opportunityId]['line_items'][] = [
                'name' => (string) $item->name,
                'quantity_allocated' => (int) $pivot->quantity_allocated,
                'revenue_minor' => $lineRevenue,
            ];
        }

        return $byOpportunity;
    }

    private function allocatedLineRevenueMinor(OpportunityItem $item, int $quantityAllocated): int
    {
        $lineQuantity = BigDecimal::of($item->quantity);

        if ($lineQuantity->isLessThanOrEqualTo(0)) {
            return 0;
        }

        $share = BigDecimal::of($quantityAllocated)
            ->dividedBy($lineQuantity, 12, RoundingMode::HALF_UP);

        return (int) BigDecimal::of($item->total)
            ->multipliedBy($share)
            ->toScale(0, RoundingMode::HALF_UP)
            ->toInt();
    }
}
