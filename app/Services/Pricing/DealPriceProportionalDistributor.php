<?php

namespace App\Services\Pricing;

use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Services\Opportunities\OpportunityTotalsCalculator;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Back-calculates per-line {@see OpportunityItem::$deal_price_discount_percent}
 * values so discountable lines sum to a target deal price using largest-remainder
 * apportionment on post-category line nets.
 */
class DealPriceProportionalDistributor
{
    public function __construct(
        private readonly OpportunityTotalsCalculator $calculator,
        private readonly DealPriceValidatorRegistry $validators,
    ) {}

    /**
     * @return array{
     *     original_total: int,
     *     non_discountable_total: int,
     *     fixed_total: int,
     *     applications: list<array{
     *         opportunity_item_id: int,
     *         state_id: int,
     *         deal_price_discount_percent: ?string
     *     }>
     * }
     */
    public function distribute(
        Opportunity $opportunity,
        int $dealTotalMinor,
        ?OpportunityItem $groupItem = null,
    ): array {
        foreach ($this->validators->all() as $validator) {
            $validator->validate($opportunity, $dealTotalMinor, $groupItem);
        }

        $opportunity->loadMissing(['items.item']);
        $scoped = $this->scopedPriceableItems($opportunity->items, $groupItem);

        $baselines = $this->calculator->baselinePostCategoryNets($opportunity, $scoped);

        $nonDiscountableTotal = 0;
        $fixedTotal = 0;
        /** @var array<int, int> $adjustableWeights */
        $adjustableWeights = [];

        foreach ($scoped as $item) {
            if ($item->is_optional) {
                continue;
            }

            $net = $baselines[(int) $item->id] ?? 0;

            if (! $this->isDiscountable($item)) {
                $nonDiscountableTotal += $net;

                continue;
            }

            if ($item->is_discount_manual) {
                $fixedTotal += $net;

                continue;
            }

            if ($net <= 0) {
                continue;
            }

            $adjustableWeights[(int) $item->id] = $net;
        }

        if ($dealTotalMinor < $nonDiscountableTotal) {
            throw ValidationException::withMessages([
                'deal_total' => sprintf(
                    'Deal price cannot be below the non-discountable total (%s).',
                    number_format($nonDiscountableTotal / 100, 2, '.', ''),
                ),
            ]);
        }

        $targetAdjustable = $dealTotalMinor - $nonDiscountableTotal - $fixedTotal;

        if ($targetAdjustable < 0) {
            throw ValidationException::withMessages([
                'deal_total' => 'Deal price cannot be below the total of non-discountable and manually discounted lines.',
            ]);
        }

        $originalTotal = $nonDiscountableTotal + $fixedTotal + array_sum($adjustableWeights);
        $targetAllocations = $this->largestRemainderApportion($adjustableWeights, $targetAdjustable);

        /** @var list<array{opportunity_item_id: int, state_id: int, deal_price_discount_percent: ?string}> $applications */
        $applications = [];

        foreach ($scoped as $item) {
            if ($item->is_optional || $item->is_discount_manual || ! $this->isDiscountable($item)) {
                continue;
            }

            $itemId = (int) $item->id;

            if (! isset($adjustableWeights[$itemId])) {
                continue;
            }

            $baseline = $adjustableWeights[$itemId];
            $targetNet = $targetAllocations[$itemId] ?? 0;

            $applications[] = [
                'opportunity_item_id' => $itemId,
                'state_id' => (int) $item->state_id,
                'deal_price_discount_percent' => $this->percentForTargetNet($baseline, $targetNet),
            ];
        }

        return [
            'original_total' => $originalTotal,
            'non_discountable_total' => $nonDiscountableTotal,
            'fixed_total' => $fixedTotal,
            'applications' => $applications,
        ];
    }

    /**
     * @return Collection<int, OpportunityItem>
     */
    public function scopedItemsForGroup(Opportunity $opportunity, OpportunityItem $groupItem): Collection
    {
        $opportunity->loadMissing(['items.item']);

        return $this->scopedPriceableItems($opportunity->items, $groupItem);
    }

    /**
     * @param  Collection<int, OpportunityItem>  $items
     * @return Collection<int, OpportunityItem>
     */
    private function scopedPriceableItems(Collection $items, ?OpportunityItem $groupItem): Collection
    {
        if ($groupItem === null) {
            return $items->filter(fn (OpportunityItem $item): bool => $item->item_type->isPriceable())->values();
        }

        $groupPath = (string) $groupItem->path;
        $groupId = (int) $groupItem->id;

        return $items->filter(function (OpportunityItem $item) use ($groupPath, $groupId): bool {
            if (! $item->item_type->isPriceable()) {
                return false;
            }

            if ((int) ($item->revenue_group_id ?? 0) === $groupId) {
                return true;
            }

            return $groupPath !== '' && str_starts_with((string) $item->path, $groupPath);
        })->values();
    }

    private function isDiscountable(OpportunityItem $item): bool
    {
        if (! $item->isProductBacked()) {
            return true;
        }

        $product = $item->relationLoaded('item') ? $item->item : null;

        if (! $product instanceof Product) {
            $product = Product::query()->find($item->itemable_id);
        }

        if (! $product instanceof Product) {
            return true;
        }

        return $product->discountable;
    }

    private function percentForTargetNet(int $baselineNet, int $targetNet): ?string
    {
        if ($baselineNet <= 0) {
            return null;
        }

        if ($targetNet >= $baselineNet) {
            return '0.00';
        }

        $discount = $baselineNet - $targetNet;

        return bcdiv(
            bcmul((string) $discount, '100', 12),
            (string) $baselineNet,
            10,
        );
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
}
