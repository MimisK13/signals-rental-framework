<?php

namespace App\Services\Tax;

use App\Data\Tax\TaxZoneRuleMatchRequest;
use App\Enums\TaxBuyerType;
use App\Models\TaxRule;
use Illuminate\Database\Eloquent\Builder;

/**
 * Pure lookup layer for zone-based tax rule matching. Does not calculate tax
 * amounts — wiring into DocumentTaxBreakdown arrives in P13.
 */
class TaxZoneRuleResolver
{
    public function resolve(TaxZoneRuleMatchRequest $request): ?TaxRule
    {
        return $this->matchingQuery($request)
            ->orderByDesc('priority')
            ->orderBy('id')
            ->first();
    }

    /**
     * @return Builder<TaxRule>
     */
    public function matchingQuery(TaxZoneRuleMatchRequest $request): Builder
    {
        return TaxRule::query()
            ->where('is_active', true)
            ->whereNotNull('seller_zone_id')
            ->whereNotNull('buyer_zone_id')
            ->whereNotNull('result_type')
            ->where('seller_zone_id', $request->seller_zone_id)
            ->where('buyer_zone_id', $request->buyer_zone_id)
            ->where(function (Builder $query) use ($request): void {
                $query->whereNull('buyer_type')
                    ->orWhere('buyer_type', $request->buyer_type->value);
            })
            ->where(function (Builder $query) use ($request): void {
                $query->whereNull('has_tax_id')
                    ->orWhere('has_tax_id', $request->has_tax_id);
            })
            ->when(
                $request->tax_category_id !== null,
                fn (Builder $query): Builder => $query->where(function (Builder $inner) use ($request): void {
                    $inner->whereNull('tax_category_id')
                        ->orWhere('tax_category_id', $request->tax_category_id);
                }),
            );
    }

    public function resolveFromContext(
        int $sellerZoneId,
        int $buyerZoneId,
        TaxBuyerType $buyerType,
        bool $hasTaxId = false,
        ?int $taxCategoryId = null,
    ): ?TaxRule {
        return $this->resolve(new TaxZoneRuleMatchRequest(
            seller_zone_id: $sellerZoneId,
            buyer_zone_id: $buyerZoneId,
            buyer_type: $buyerType,
            has_tax_id: $hasTaxId,
            tax_category_id: $taxCategoryId,
        ));
    }
}
