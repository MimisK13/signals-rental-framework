<?php

namespace App\Services\Pricing;

use App\Models\BundleRule;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\ValueObjects\Pricing\DiscountCriteriaContext;
use Illuminate\Support\Collection;

/**
 * Stage 1.5 — bundle pricing applied at the revenue-group level.
 *
 * Sits after base rate resolution (Stage 1) and before discount rule evaluation
 * (Stage 2). Bundle rules define a product set and either a fixed bundle price
 * or a bundle-wide percentage reduction; matched constituent line nets are
 * re-attributed via largest-remainder apportionment (P1 rounding seam).
 */
class BundlePricingApplicator
{
    public function __construct(
        private readonly CriteriaMatcher $matcher,
        private readonly RoundingStrategy $roundingStrategy,
    ) {}

    /**
     * @param  array<int, int>  $baseSubtotalsByItemId  Pre-discount line nets keyed by item id.
     * @param  Collection<int, OpportunityItem>  $items
     * @return array<int, int> Adjusted pre-discount nets keyed by item id.
     */
    public function apply(
        Opportunity $opportunity,
        Collection $items,
        array $baseSubtotalsByItemId,
        string $currencyCode,
    ): array {
        $adjusted = $baseSubtotalsByItemId;

        $rules = BundleRule::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($rules->isEmpty()) {
            return $adjusted;
        }

        /** @var Collection<int|string, Collection<int, OpportunityItem>> $byGroup */
        $byGroup = $items->groupBy(fn (OpportunityItem $item): int => (int) ($item->revenue_group_id ?? 0));

        foreach ($rules as $rule) {
            foreach ($byGroup as $groupItems) {
                $memberIds = $this->matchingMemberIds($rule, $groupItems);

                if ($memberIds === []) {
                    continue;
                }

                $currentSum = array_sum(array_map(
                    static fn (int $id): int => $adjusted[$id] ?? 0,
                    $memberIds,
                ));

                if ($currentSum <= 0) {
                    continue;
                }

                $targetSum = $this->targetBundleTotal($rule, $currentSum, $currencyCode);

                $adjusted = $this->distributeTarget(
                    $adjusted,
                    $memberIds,
                    $targetSum,
                );
            }
        }

        return $adjusted;
    }

    /**
     * @param  Collection<int, OpportunityItem>  $groupItems
     * @return list<int>
     */
    private function matchingMemberIds(BundleRule $rule, Collection $groupItems): array
    {
        $requiredProductIds = array_map(
            intval(...),
            $rule->criteria['product_ids'] ?? [],
        );

        if ($requiredProductIds !== []) {
            $presentProductIds = $groupItems
                ->filter(fn (OpportunityItem $item): bool => $item->isProductBacked())
                ->map(fn (OpportunityItem $item): int => (int) $item->itemable_id)
                ->unique()
                ->all();

            foreach ($requiredProductIds as $requiredId) {
                if (! in_array($requiredId, $presentProductIds, true)) {
                    return [];
                }
            }
        }

        $matched = [];

        foreach ($groupItems as $item) {
            if (! $item->item_type->isPriceable()) {
                continue;
            }

            if (! $this->matcher->matches($rule->criteria, $this->contextFromItem($item))) {
                continue;
            }

            if ($requiredProductIds !== [] && $item->isProductBacked()) {
                if (! in_array((int) $item->itemable_id, $requiredProductIds, true)) {
                    continue;
                }
            }

            $matched[] = (int) $item->id;
        }

        return $matched;
    }

    private function targetBundleTotal(BundleRule $rule, int $currentSum, string $currencyCode): int
    {
        if ($rule->isFixed()) {
            return (int) $rule->bundle_fixed_price_minor;
        }

        if ($rule->isPercent()) {
            $rawDiscount = bcdiv(
                bcmul((string) $currentSum, (string) $rule->bundle_percent, 10),
                '100',
                10,
            );
            $discount = $this->roundingStrategy->roundMinorAmount($rawDiscount, $currencyCode);

            return max(0, $currentSum - $discount);
        }

        return $currentSum;
    }

    /**
     * @param  list<int>  $memberIds
     * @param  array<int, int>  $nets
     * @return array<int, int>
     */
    private function distributeTarget(array $nets, array $memberIds, int $targetSum): array
    {
        $weights = [];

        foreach ($memberIds as $id) {
            $weights[$id] = max(0, $nets[$id] ?? 0);
        }

        $totalWeight = array_sum($weights);

        if ($totalWeight === 0) {
            return $nets;
        }

        $allocations = $this->largestRemainderApportion($weights, $targetSum);

        foreach ($allocations as $id => $amount) {
            $nets[$id] = $amount;
        }

        return $nets;
    }

    /**
     * @param  array<int, int>  $weights
     * @return array<int, int>
     */
    private function largestRemainderApportion(array $weights, int $targetSum): array
    {
        $totalWeight = array_sum($weights);

        if ($totalWeight === 0) {
            return array_fill_keys(array_keys($weights), 0);
        }

        $floors = [];
        $remainders = [];

        foreach ($weights as $id => $weight) {
            $exact = bcdiv(
                bcmul((string) $targetSum, (string) $weight, 10),
                (string) $totalWeight,
                10,
            );
            $floor = (int) bcadd($exact, '0', 0);
            $floors[$id] = $floor;
            $remainders[$id] = bcsub($exact, (string) $floor, 10);
        }

        $assigned = array_sum($floors);
        $remaining = $targetSum - $assigned;

        if ($remaining === 0) {
            return $floors;
        }

        $ids = array_keys($weights);
        usort($ids, function (int $left, int $right) use ($remainders): int {
            $cmp = bccomp($remainders[$right], $remainders[$left], 10);

            return $cmp !== 0 ? $cmp : $left <=> $right;
        });

        $step = $remaining > 0 ? 1 : -1;
        $count = abs($remaining);

        for ($i = 0; $i < $count; $i++) {
            $id = $ids[$i % count($ids)];
            $floors[$id] += $step;
        }

        return $floors;
    }

    private function contextFromItem(OpportunityItem $item): DiscountCriteriaContext
    {
        $productGroupId = null;
        $productId = null;

        if ($item->isProductBacked()) {
            $product = $item->relationLoaded('item') ? $item->item : null;

            if (! $product instanceof Product) {
                $product = Product::query()->find($item->itemable_id);
            }

            if ($product instanceof Product) {
                $productId = (int) $product->id;
                $productGroupId = $product->product_group_id !== null ? (int) $product->product_group_id : null;
            }
        }

        return new DiscountCriteriaContext(
            product_group_id: $productGroupId,
            product_id: $productId,
        );
    }
}
