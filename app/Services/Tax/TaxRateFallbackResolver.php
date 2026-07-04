<?php

namespace App\Services\Tax;

use App\Enums\TaxCategoryType;
use App\Enums\TaxResultType;
use App\Models\TaxCategory;
use App\Models\TaxRate;
use App\Models\TaxRule;

/**
 * Resolves a named {@see TaxRate} when zone rules match but the org × product
 * class matrix cannot refine a rate (missing classes or no matching rule).
 *
 * Aligns with the framework plan: standard zone results fall back to the seeded
 * Standard / Reduced / Zero rates, optionally steered by the product's sale tax
 * category type.
 */
class TaxRateFallbackResolver
{
    public function resolve(
        TaxRule $zoneRule,
        TaxResultType $resultType,
        ?int $productTaxCategoryId,
    ): ?TaxRate {
        if ($zoneRule->tax_rate_id !== null) {
            $explicitRate = TaxRate::query()
                ->whereKey($zoneRule->tax_rate_id)
                ->where('is_active', true)
                ->first();

            if ($explicitRate !== null) {
                return $explicitRate;
            }
        }

        $rateName = $this->resolveRateName($resultType, $productTaxCategoryId);

        return TaxRate::query()
            ->where('name', $rateName)
            ->where('is_active', true)
            ->orderByDesc('id')
            ->first();
    }

    private function resolveRateName(TaxResultType $resultType, ?int $productTaxCategoryId): string
    {
        if ($productTaxCategoryId !== null) {
            $category = TaxCategory::query()->find($productTaxCategoryId);

            if ($category !== null) {
                $categoryType = $this->resolveCategoryType($category);

                return match ($categoryType) {
                    TaxCategoryType::Reduced => 'Reduced',
                    TaxCategoryType::Zero => 'Zero',
                    TaxCategoryType::Exempt => 'Zero',
                    TaxCategoryType::Standard => 'Standard',
                    null => 'Standard',
                };
            }
        }

        return match ($resultType) {
            TaxResultType::Standard => 'Standard',
            TaxResultType::ZeroRated => 'Zero',
            default => 'Zero',
        };
    }

    private function resolveCategoryType(TaxCategory $category): ?TaxCategoryType
    {
        $type = $category->getAttribute('type');

        if ($type instanceof TaxCategoryType) {
            return $type;
        }

        return TaxCategoryType::tryFrom((string) $type);
    }
}
