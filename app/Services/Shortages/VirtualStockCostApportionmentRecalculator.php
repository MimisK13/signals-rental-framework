<?php

namespace App\Services\Shortages;

use App\Actions\VirtualStock\ApportionVirtualStockCost;
use App\Models\OpportunityItem;
use App\Models\VirtualStockIntake;
use App\Verbs\Events\Opportunities\ItemDatesChanged;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Thunk\Verbs\Facades\Verbs;

/**
 * Re-runs virtual stock cost apportionment when a participating line item's hire
 * window changes and the intake uses a date-sensitive strategy (§6.4).
 *
 * Invoked from {@see ItemDatesChanged::handle()}
 * inside {@see Verbs::unlessReplaying()} so truncate + replay rebuilds costs from
 * the persisted CostAdded/CostUpdated stream without double-apportioning.
 */
class VirtualStockCostApportionmentRecalculator
{
    /** @var list<string> */
    private const DATE_SENSITIVE_STRATEGIES = [
        'proportional_quantity_duration',
    ];

    public function __construct(
        private readonly ApportionVirtualStockCost $apportionVirtualStockCost,
    ) {}

    public function forItem(OpportunityItem $item): void
    {
        Verbs::unlessReplaying(function () use ($item): void {
            foreach ($this->affectedIntakes((int) $item->opportunity_id) as $intake) {
                ($this->apportionVirtualStockCost)($intake, authorize: false);
            }
        });
    }

    /**
     * @return Collection<int, VirtualStockIntake>
     */
    private function affectedIntakes(int $opportunityId): Collection
    {
        return VirtualStockIntake::query()
            ->whereIn('status', ['confirmed', 'received'])
            ->where(function (Builder $query) use ($opportunityId): void {
                $query->whereHas(
                    'shortageResolution.items.opportunityItem',
                    fn (Builder $itemQuery): Builder => $itemQuery->where('opportunity_id', $opportunityId),
                )->orWhereHas(
                    'purchaseOrder',
                    fn (Builder $poQuery): Builder => $poQuery->where('opportunity_id', $opportunityId),
                );
            })
            ->get()
            ->filter(fn (VirtualStockIntake $intake): bool => $this->usesDateSensitiveStrategy($intake))
            ->values();
    }

    private function usesDateSensitiveStrategy(VirtualStockIntake $intake): bool
    {
        $strategyKey = is_string($intake->cost_apportionment_strategy) && $intake->cost_apportionment_strategy !== ''
            ? $intake->cost_apportionment_strategy
            : (string) settings(
                'shortages.cost_apportionment_strategy',
                config('shortages.cost_apportionment_strategy', 'primary_job'),
            );

        return in_array($strategyKey, self::DATE_SENSITIVE_STRATEGIES, true);
    }
}
