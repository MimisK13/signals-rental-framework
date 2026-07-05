<?php

namespace App\Services\Pricing;

use App\Models\Opportunity;
use App\Models\OpportunityItem;
use Illuminate\Support\Collection;

/**
 * Builds aggregate quantity maps for volume discount rule scopes.
 */
class QuantityContextResolver
{
    /**
     * @param  Collection<int, OpportunityItem>  $items
     * @return array{
     *     by_product: array<int, int>,
     *     by_group: array<int, int>
     * }
     */
    public function resolve(Opportunity $opportunity, Collection $items): array
    {
        $byProduct = [];
        $byGroup = [];

        foreach ($items as $item) {
            if (! $item->item_type->isPriceable()) {
                continue;
            }

            $units = max(0, (int) round((float) $item->quantity));

            if ($item->isProductBacked()) {
                $productId = (int) $item->itemable_id;
                $byProduct[$productId] = ($byProduct[$productId] ?? 0) + $units;
            }

            $groupKey = $item->revenue_group_id ?? 0;
            $byGroup[$groupKey] = ($byGroup[$groupKey] ?? 0) + $units;
        }

        return [
            'by_product' => $byProduct,
            'by_group' => $byGroup,
        ];
    }
}
