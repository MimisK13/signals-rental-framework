<?php

namespace App\Actions\Members;

use App\Models\PriceCategoryTier;
use Illuminate\Validation\ValidationException;

class MemberPriceCategoryTierValidator
{
    public static function assertTierBelongsToCategory(?int $tierId, ?int $categoryId): void
    {
        if ($tierId === null) {
            return;
        }

        if ($categoryId === null) {
            throw ValidationException::withMessages([
                'price_category_tier_id' => ['A price category tier requires a price category.'],
            ]);
        }

        $tier = PriceCategoryTier::query()->find($tierId);

        if ($tier === null || $tier->price_category_id !== $categoryId) {
            throw ValidationException::withMessages([
                'price_category_tier_id' => ['The selected price category tier does not belong to the price category.'],
            ]);
        }
    }
}
