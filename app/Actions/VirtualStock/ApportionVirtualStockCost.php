<?php

namespace App\Actions\VirtualStock;

use App\Concerns\CommitsVerbsEvents;
use App\Data\VirtualStock\ApportionVirtualStockCostResult;
use App\Enums\OpportunityCostType;
use App\Models\OpportunityCost;
use App\Models\ShortageResolution;
use App\Models\VirtualStockIntake;
use App\Services\SequenceAllocator;
use App\Services\Shortages\CostApportionmentContextBuilder;
use App\Services\Shortages\CostApportionmentRegistry;
use App\Verbs\Events\Opportunities\CostAdded;
use App\Verbs\Events\Opportunities\CostRemoved;
use App\Verbs\Events\Opportunities\CostUpdated;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

/**
 * Apportions a virtual stock intake's total cost across participating opportunities
 * by firing CostAdded / CostUpdated / CostRemoved on the Opportunity aggregate.
 *
 * Idempotent: diffs desired allocations against existing intake-linked costs.
 * Strategy resolution order: explicit override → intake column → settings/config
 * default (`shortages.cost_apportionment_strategy`, default `primary_job`).
 *
 * Remainder-penny distribution is deterministic via {@see MoneyApportionment}
 * (largest fractional part, tie-break ascending opportunity id).
 */
class ApportionVirtualStockCost
{
    use CommitsVerbsEvents;

    public function __construct(
        private readonly CostApportionmentContextBuilder $contextBuilder,
        private readonly CostApportionmentRegistry $registry,
    ) {}

    public function __invoke(
        VirtualStockIntake $intake,
        ?string $strategyKey = null,
        bool $authorize = true,
    ): ApportionVirtualStockCostResult {
        if ($authorize) {
            Gate::authorize('costs.view');
        }

        if (! $this->shouldApportion($intake)) {
            return new ApportionVirtualStockCostResult(
                virtual_stock_intake_id: (int) $intake->id,
                strategy_key: $this->resolveStrategyKey($intake, $strategyKey),
                allocations: [],
            );
        }

        $context = $this->contextBuilder->build($intake->refresh());
        $resolvedStrategy = $this->resolveStrategyKey($intake, $strategyKey);
        $strategy = $this->registry->get($resolvedStrategy);
        $desired = $strategy->calculate($context);

        $manualMismatch = false;
        if ($strategy->requiresManualInput()) {
            $allocatedTotal = array_sum($desired);
            $manualMismatch = $allocatedTotal !== $context->totalCostMinor;

            if ($manualMismatch) {
                Log::warning('Manual virtual stock cost apportionment does not sum to intake total.', [
                    'virtual_stock_intake_id' => $intake->id,
                    'intake_total_minor' => $context->totalCostMinor,
                    'allocated_total_minor' => $allocatedTotal,
                ]);
            }
        }

        $this->persistStrategyMetadata($intake, $resolvedStrategy);
        $this->syncCosts($intake, $desired, $context->totalCostMinor);

        return new ApportionVirtualStockCostResult(
            virtual_stock_intake_id: (int) $intake->id,
            strategy_key: $resolvedStrategy,
            allocations: $desired,
            manual_mismatch_warning: $manualMismatch,
            intake_total_minor: $context->totalCostMinor,
            allocated_total_minor: array_sum($desired),
        );
    }

    public function removeForIntake(VirtualStockIntake $intake, bool $authorize = true): void
    {
        if ($authorize) {
            Gate::authorize('costs.view');
        }

        $existing = OpportunityCost::query()
            ->where('virtual_stock_intake_id', $intake->id)
            ->get();

        if ($existing->isEmpty()) {
            return;
        }

        $this->commitVerbs(function () use ($existing): void {
            foreach ($existing as $cost) {
                CostRemoved::fire(opportunity_cost_id: $cost->state_id);
            }
        });
    }

    private function shouldApportion(VirtualStockIntake $intake): bool
    {
        return in_array($intake->status, ['confirmed', 'received'], true)
            && (int) $intake->quantity > 0
            && (int) $intake->unit_cost >= 0;
    }

    private function resolveStrategyKey(VirtualStockIntake $intake, ?string $explicit): string
    {
        if ($explicit !== null && $explicit !== '' && $this->registry->has($explicit)) {
            return $explicit;
        }

        if (is_string($intake->cost_apportionment_strategy) && $intake->cost_apportionment_strategy !== '') {
            return $intake->cost_apportionment_strategy;
        }

        return (string) settings(
            'shortages.cost_apportionment_strategy',
            config('shortages.cost_apportionment_strategy', 'primary_job'),
        );
    }

    private function persistStrategyMetadata(VirtualStockIntake $intake, string $strategyKey): void
    {
        ShortageResolution::query()
            ->where('virtual_stock_intake_id', $intake->id)
            ->get()
            ->each(function (ShortageResolution $resolution) use ($strategyKey): void {
                $metadata = $resolution->metadata ?? [];
                $metadata['apportionment_strategy'] = $strategyKey;
                $resolution->forceFill(['metadata' => $metadata])->saveQuietly();
            });
    }

    /**
     * @param  array<int, int>  $desired
     */
    private function syncCosts(VirtualStockIntake $intake, array $desired, int $intakeTotalMinor): void
    {
        $existing = OpportunityCost::query()
            ->where('virtual_stock_intake_id', $intake->id)
            ->get()
            ->keyBy('opportunity_id');

        $costType = $intake->purchase_order_id !== null
            ? OpportunityCostType::Subhire
            : OpportunityCostType::VirtualStock;

        $description = $this->buildDescription($intake);
        $sortOrder = 9000 + ((int) $intake->id % 1000);

        $this->commitVerbs(function () use (
            $intake,
            $desired,
            $existing,
            $costType,
            $description,
            $sortOrder,
            $intakeTotalMinor,
        ): void {
            $allocator = app(SequenceAllocator::class);

            foreach ($desired as $opportunityId => $amountMinor) {
                $current = $existing->get($opportunityId);

                if ($amountMinor <= 0) {
                    continue;
                }

                if ($current === null) {
                    $costId = $allocator->next('opportunity_costs');

                    CostAdded::fire(
                        opportunity_cost_id: $costId,
                        opportunity_id: $opportunityId,
                        virtual_stock_intake_id: (int) $intake->id,
                        description: $description,
                        cost_type: $costType->value,
                        amount: $amountMinor,
                        quantity: '1',
                        sort_order: $sortOrder,
                        notes: sprintf('Apportioned from intake %s (total %d minor units)', $intake->number, $intakeTotalMinor),
                    );

                    continue;
                }

                if ($current->amount === $amountMinor
                    && $current->description === $description
                    && $current->cost_type === $costType) {
                    continue;
                }

                CostUpdated::fire(
                    opportunity_cost_id: $current->state_id,
                    virtual_stock_intake_id: (int) $intake->id,
                    description: $description,
                    cost_type: $costType->value,
                    transaction_type: $current->transaction_type->value,
                    amount: $amountMinor,
                    quantity: '1',
                    is_optional: $current->is_optional,
                    sort_order: $sortOrder,
                    notes: sprintf('Apportioned from intake %s (total %d minor units)', $intake->number, $intakeTotalMinor),
                );
            }

            foreach ($existing as $opportunityId => $cost) {
                if (! array_key_exists($opportunityId, $desired) || $desired[$opportunityId] <= 0) {
                    CostRemoved::fire(opportunity_cost_id: $cost->state_id);
                }
            }
        });
    }

    private function buildDescription(VirtualStockIntake $intake): string
    {
        $intake->loadMissing(['purchaseOrder.supplier']);

        $supplier = $intake->purchaseOrder?->supplier?->name;

        if (is_string($supplier) && $supplier !== '') {
            return sprintf('%s — %s', $intake->number, $supplier);
        }

        return $intake->number;
    }
}
