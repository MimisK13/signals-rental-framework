<?php

namespace App\Services\Pricing;

use App\Enums\LineItemTransactionType;
use App\Enums\RateTransactionType;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\OpportunityItemSurcharge;
use App\Models\OpportunitySurchargeGroup;
use App\Models\Product;
use App\Models\ProductTaxClass;
use App\Models\Surcharge;
use App\Models\SurchargeGroup;
use App\ValueObjects\Pricing\DiscountCriteriaContext;
use App\ValueObjects\Pricing\EvaluatedSurcharge;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Evaluates active surcharge definitions for opportunity line items.
 *
 * Surcharges are additive (all matching surcharges apply). Percent surcharges
 * apply to the post-discount line net; fixed surcharges apply per quantity unit.
 * Temporal validity and {@see CriteriaMatcher} predicates mirror discount rules.
 */
class SurchargeEvaluator
{
    public function __construct(
        private readonly CriteriaMatcher $matcher,
        private readonly RoundingStrategy $roundingStrategy,
    ) {}

    /**
     * @param  Collection<int, Surcharge>  $surcharges
     * @return list<EvaluatedSurcharge>
     */
    public function evaluateForItem(
        OpportunityItem $item,
        Opportunity $opportunity,
        Collection $surcharges,
        int $postDiscountNetMinor,
        string $currencyCode,
        CarbonInterface $at,
        ?int $fallbackProductTaxClassId,
    ): array {
        if (! $this->isSurchargeable($item)) {
            return [];
        }

        $quantityUnits = max(0, (int) round((float) $item->quantity));
        $context = $this->contextFromItem($item);
        $evaluated = [];

        foreach ($surcharges as $surcharge) {
            if (! $this->isTemporallyValid($surcharge, $at)) {
                continue;
            }

            if (! $this->matchesSurcharge($surcharge, $context)) {
                continue;
            }

            $amount = $this->calculateAmount(
                $surcharge,
                $postDiscountNetMinor,
                $quantityUnits,
                $currencyCode,
            );

            if ($amount === 0) {
                continue;
            }

            $evaluated[] = new EvaluatedSurcharge(
                surcharge_id: (int) $surcharge->id,
                name: $surcharge->name,
                surcharge_type: $surcharge->typeValue(),
                percent_value: $surcharge->percent_value !== null ? (string) $surcharge->percent_value : null,
                fixed_value_minor: $surcharge->fixed_value_minor,
                amount_minor: $amount,
                product_tax_class_id: $this->resolveTaxClassId(
                    $surcharge,
                    $fallbackProductTaxClassId,
                ),
            );
        }

        return $evaluated;
    }

    /**
     * Resolve which surcharge group ids are active on the opportunity.
     *
     * Default groups apply when no explicit pivot row exists; configured pivots
     * override the default with their `is_active` flag.
     *
     * @return list<int>
     */
    public function activeGroupIds(Opportunity $opportunity): array
    {
        /** @var Collection<int, OpportunitySurchargeGroup> $pivots */
        $pivots = OpportunitySurchargeGroup::query()
            ->where('opportunity_id', $opportunity->id)
            ->get()
            ->keyBy('surcharge_group_id');

        $groups = SurchargeGroup::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $active = [];

        foreach ($groups as $group) {
            $pivot = $pivots->get($group->id);

            if ($pivot !== null) {
                if ($pivot->is_active) {
                    $active[] = (int) $group->id;
                }

                continue;
            }

            if ($group->is_default) {
                $active[] = (int) $group->id;
            }
        }

        return $active;
    }

    /**
     * @param  list<int>  $activeGroupIds
     * @return Collection<int, Surcharge>
     */
    public function surchargesForGroups(array $activeGroupIds): Collection
    {
        if ($activeGroupIds === []) {
            return collect();
        }

        return Surcharge::query()
            ->whereIn('surcharge_group_id', $activeGroupIds)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function evaluationInstant(OpportunityItem $item, Opportunity $opportunity): CarbonInterface
    {
        return $item->starts_at ?? $opportunity->starts_at ?? now('UTC');
    }

    /**
     * Merge auto-evaluated surcharges with per-line overrides/disables.
     *
     * @param  list<EvaluatedSurcharge>  $autoEvaluated
     * @param  Collection<int, OpportunityItemSurcharge>  $existingRows  keyed by surcharge_id
     * @return list<EvaluatedSurcharge>
     */
    public function mergeWithOverrides(array $autoEvaluated, Collection $existingRows): array
    {
        $autoById = [];

        foreach ($autoEvaluated as $evaluated) {
            $autoById[$evaluated->surcharge_id] = $evaluated;
        }

        $merged = [];
        $handled = [];

        foreach ($existingRows as $surchargeId => $row) {
            if ($row->is_disabled) {
                $handled[$surchargeId] = true;

                continue;
            }

            if ($row->is_manual_override) {
                $merged[] = new EvaluatedSurcharge(
                    surcharge_id: (int) $row->surcharge_id,
                    name: $row->resolvedName(),
                    surcharge_type: $row->resolvedSurchargeType(),
                    percent_value: $row->resolvedPercentValue(),
                    fixed_value_minor: $row->resolvedFixedValueMinor(),
                    amount_minor: (int) $row->amount,
                    product_tax_class_id: $row->product_tax_class_id,
                );
                $handled[$surchargeId] = true;

                continue;
            }

            if (isset($autoById[$surchargeId])) {
                $merged[] = $autoById[$surchargeId];
                $handled[$surchargeId] = true;
            }
        }

        foreach ($autoEvaluated as $evaluated) {
            if (isset($handled[$evaluated->surcharge_id])) {
                continue;
            }

            $merged[] = $evaluated;
        }

        return $merged;
    }

    public function resolveTaxClassId(Surcharge $surcharge, ?int $fallbackProductTaxClassId): ?int
    {
        if ($surcharge->product_tax_class_id !== null) {
            return (int) $surcharge->product_tax_class_id;
        }

        return $fallbackProductTaxClassId;
    }

    public function defaultProductTaxClassId(): ?int
    {
        return ProductTaxClass::query()->where('is_default', true)->value('id');
    }

    private function isSurchargeable(OpportunityItem $item): bool
    {
        if (! $item->item_type->isPriceable()) {
            return false;
        }

        return match ($item->transaction_type) {
            LineItemTransactionType::Rental,
            LineItemTransactionType::Sale,
            LineItemTransactionType::Service => true,
            default => false,
        };
    }

    private function matchesSurcharge(Surcharge $surcharge, DiscountCriteriaContext $context): bool
    {
        $criteria = $surcharge->criteria ?? [];

        if ($criteria === [] && ! $surcharge->is_default) {
            return false;
        }

        return $this->matcher->matches($criteria, $context);
    }

    private function isTemporallyValid(Surcharge $surcharge, CarbonInterface $at): bool
    {
        if ($surcharge->valid_from !== null && $at->lt($surcharge->valid_from)) {
            return false;
        }

        if ($surcharge->valid_to !== null && $at->gt($surcharge->valid_to)) {
            return false;
        }

        return true;
    }

    private function calculateAmount(
        Surcharge $surcharge,
        int $postDiscountNetMinor,
        int $quantityUnits,
        string $currencyCode,
    ): int {
        if ($surcharge->isPercent()) {
            $raw = bcdiv(
                bcmul((string) $postDiscountNetMinor, (string) $surcharge->percent_value, 10),
                '100',
                10,
            );

            return $this->roundingStrategy->roundMinorAmount($raw, $currencyCode);
        }

        if ($surcharge->isFixed()) {
            return (int) $surcharge->fixed_value_minor * max(1, $quantityUnits);
        }

        return 0;
    }

    private function contextFromItem(OpportunityItem $item): DiscountCriteriaContext
    {
        $productGroupId = null;
        $tagNames = [];
        $productId = null;

        if ($item->isProductBacked()) {
            $product = $item->relationLoaded('item') ? $item->item : null;

            if (! $product instanceof Product) {
                $product = Product::query()->find($item->itemable_id);
            }

            if ($product instanceof Product) {
                $productId = (int) $product->id;
                $productGroupId = $product->product_group_id !== null ? (int) $product->product_group_id : null;
                $tagNames = [];
                $tags = $product->getAttribute('tag_list');

                if (is_array($tags)) {
                    foreach ($tags as $tag) {
                        if (is_string($tag)) {
                            $tagNames[] = $tag;
                        }
                    }
                }
            }
        }

        return new DiscountCriteriaContext(
            product_group_id: $productGroupId,
            tag_names: $tagNames,
            product_id: $productId,
            transaction_type: $this->mapTransactionType($item->transaction_type),
        );
    }

    private function mapTransactionType(LineItemTransactionType $type): string
    {
        return match ($type) {
            LineItemTransactionType::Rental, LineItemTransactionType::SubRental => RateTransactionType::Rental->value,
            LineItemTransactionType::Sale => RateTransactionType::Sale->value,
            LineItemTransactionType::Service => RateTransactionType::Service->value,
        };
    }
}
