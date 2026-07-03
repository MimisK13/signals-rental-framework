<?php

namespace App\Services\Pricing;

use App\Models\DiscountCategory;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Verbs\Events\Opportunities\DiscountCategoryApplied;
use App\Verbs\Events\Opportunities\ItemDiscountSet;
use Illuminate\Support\Collection;

/**
 * Resolves category rules and fires the Verbs events that apply (or re-apply)
 * automatic line discounts inside an existing {@see CommitsVerbsEvents} boundary.
 */
class DiscountCategoryApplier
{
    public function __construct(
        private readonly DiscountRuleEvaluator $evaluator,
        private readonly QuantityContextResolver $quantityContextResolver,
    ) {}

    /**
     * @param  list<int>  $opportunityItemIds  Empty = all eligible lines on the opportunity.
     */
    public function apply(
        Opportunity $opportunity,
        ?int $discountCategoryId = null,
        array $opportunityItemIds = [],
        bool $resetManual = false,
    ): void {
        $categoryId = $discountCategoryId ?? $this->resolveCategoryId($opportunity);

        if ($categoryId === null) {
            return;
        }

        $category = DiscountCategory::query()
            ->with(['rules' => fn ($query) => $query->orderBy('sort_order')->orderBy('id')])
            ->find($categoryId);

        if ($category === null || ! $category->is_active) {
            return;
        }

        $opportunity->loadMissing(['items.item', 'member']);
        $items = $this->targetItems($opportunity, $opportunityItemIds);

        if ($items->isEmpty()) {
            return;
        }

        $quantityMaps = $this->quantityContextResolver->resolve($opportunity, $opportunity->items);

        /** @var list<array{item_id: int, state_id: int, discount_percent: string|null}> $applications */
        $applications = [];

        foreach ($items as $item) {
            if ($item->is_discount_manual && ! $resetManual) {
                continue;
            }

            $at = $this->evaluator->evaluationInstant($item, $opportunity);
            $percent = $this->evaluator->evaluateForItem(
                $item,
                $opportunity,
                $category->rules,
                $at,
                $quantityMaps,
            );

            if ($percent === null) {
                if ($item->discount_percent !== null && ! $item->is_discount_manual) {
                    $applications[] = [
                        'item_id' => (int) $item->id,
                        'state_id' => (int) $item->state_id,
                        'discount_percent' => null,
                    ];
                }

                continue;
            }

            $current = $item->discount_percent !== null ? (string) $item->discount_percent : null;

            if ($current === $percent && ! $item->is_discount_manual) {
                continue;
            }

            $applications[] = [
                'item_id' => (int) $item->id,
                'state_id' => (int) $item->state_id,
                'discount_percent' => $percent,
            ];
        }

        if ($applications === []) {
            return;
        }

        DiscountCategoryApplied::fire(
            opportunity_id: $opportunity->state_id,
            discount_category_id: $categoryId,
            applications: array_map(
                static fn (array $application): array => [
                    'opportunity_item_id' => $application['item_id'],
                    'discount_percent' => $application['discount_percent'],
                ],
                $applications,
            ),
            reset_manual: $resetManual,
        );

        foreach ($applications as $application) {
            ItemDiscountSet::fire(
                opportunity_item_id: $application['state_id'],
                discount_percent: $application['discount_percent'],
                is_discount_manual: false,
            );
        }
    }

    /**
     * @param  list<int>  $opportunityItemIds
     * @return Collection<int, OpportunityItem>
     */
    private function targetItems(Opportunity $opportunity, array $opportunityItemIds): Collection
    {
        $items = $opportunity->items;

        if ($opportunityItemIds !== []) {
            $ids = array_flip($opportunityItemIds);

            return $items->filter(fn (OpportunityItem $item): bool => isset($ids[$item->id]))->values();
        }

        return $items;
    }

    private function resolveCategoryId(Opportunity $opportunity): ?int
    {
        $memberId = $opportunity->member_id;

        if ($memberId === null) {
            return null;
        }

        $member = $opportunity->relationLoaded('member')
            ? $opportunity->member
            : Member::query()->find($memberId);

        return $member?->discount_category_id;
    }
}
