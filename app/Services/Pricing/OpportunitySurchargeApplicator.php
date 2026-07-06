<?php

namespace App\Services\Pricing;

use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\OpportunityItemSurcharge;
use App\Models\Product;
use App\Models\Surcharge;
use App\ValueObjects\Pricing\EvaluatedSurcharge;
use Illuminate\Support\Collection;

/**
 * Applies evaluated surcharges to a line item, persisting snapshot rows and
 * returning the merged surcharge total plus per-surcharge tax line inputs.
 */
class OpportunitySurchargeApplicator
{
    public function __construct(
        private readonly SurchargeEvaluator $evaluator,
    ) {}

    /**
     * @return array{
     *     surcharge_total: int,
     *     surcharges: list<EvaluatedSurcharge>
     * }
     */
    public function applyToItem(
        OpportunityItem $item,
        Opportunity $opportunity,
        int $postDiscountNetMinor,
        string $currencyCode,
        ?Product $product,
    ): array {
        $activeGroupIds = $this->evaluator->activeGroupIds($opportunity);

        if ($activeGroupIds === []) {
            $this->syncSnapshotRows($item, []);

            return ['surcharge_total' => 0, 'surcharges' => []];
        }

        $surcharges = $this->evaluator->surchargesForGroups($activeGroupIds);
        $at = $this->evaluator->evaluationInstant($item, $opportunity);
        $fallbackTaxClassId = $product !== null
            ? $product->tax_class_id
            : $this->evaluator->defaultProductTaxClassId();

        $autoEvaluated = $this->evaluator->evaluateForItem(
            $item,
            $opportunity,
            $surcharges,
            $postDiscountNetMinor,
            $currencyCode,
            $at,
            $fallbackTaxClassId !== null ? (int) $fallbackTaxClassId : null,
        );

        /** @var Collection<int, OpportunityItemSurcharge> $existingRows */
        $existingRows = OpportunityItemSurcharge::query()
            ->where('opportunity_item_id', $item->id)
            ->with('surcharge')
            ->get()
            ->keyBy('surcharge_id');

        $merged = $this->evaluator->mergeWithOverrides($autoEvaluated, $existingRows);
        $this->syncSnapshotRows($item, $merged, $existingRows);

        $total = array_sum(array_map(
            static fn (EvaluatedSurcharge $surcharge): int => $surcharge->amount_minor,
            $merged,
        ));

        return [
            'surcharge_total' => $total,
            'surcharges' => $merged,
        ];
    }

    /**
     * @param  list<EvaluatedSurcharge>  $merged
     * @param  Collection<int, OpportunityItemSurcharge>  $existingRows
     */
    private function syncSnapshotRows(
        OpportunityItem $item,
        array $merged,
        Collection $existingRows = new Collection,
    ): void {
        if ($existingRows->isEmpty() && $merged === []) {
            OpportunityItemSurcharge::query()
                ->where('opportunity_item_id', $item->id)
                ->where('is_manual_override', false)
                ->delete();

            return;
        }

        if ($existingRows->isEmpty()) {
            $existingRows = OpportunityItemSurcharge::query()
                ->where('opportunity_item_id', $item->id)
                ->get()
                ->keyBy('surcharge_id');
        }

        $mergedIds = [];

        foreach ($merged as $evaluated) {
            $mergedIds[$evaluated->surcharge_id] = true;
            $existing = $existingRows->get($evaluated->surcharge_id);

            if ($existing !== null) {
                if ($existing->is_manual_override || $existing->is_disabled) {
                    continue;
                }

                $updates = [
                    'amount' => $evaluated->amount_minor,
                    'product_tax_class_id' => $evaluated->product_tax_class_id,
                ];

                if ($existing->name === null) {
                    $updates = array_merge($updates, $evaluated->snapshotAttributes());
                }

                $existing->forceFill($updates)->saveQuietly();

                continue;
            }

            OpportunityItemSurcharge::query()->create([
                'opportunity_item_id' => $item->id,
                'surcharge_id' => $evaluated->surcharge_id,
                ...$evaluated->snapshotAttributes(),
                'amount' => $evaluated->amount_minor,
                'product_tax_class_id' => $evaluated->product_tax_class_id,
                'is_manual_override' => false,
                'is_disabled' => false,
            ]);
        }

        OpportunityItemSurcharge::query()
            ->where('opportunity_item_id', $item->id)
            ->where('is_manual_override', false)
            ->whereNotIn('surcharge_id', array_keys($mergedIds))
            ->delete();
    }
}
