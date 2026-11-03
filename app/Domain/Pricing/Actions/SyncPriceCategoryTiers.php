<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Actions;

use App\Domain\Pricing\Models\PriceCategory;

class SyncPriceCategoryTiers
{
    /**
     * @param  list<array{tier_number: int, label?: string|null, multiplier: string|float|int, sort_order?: int}>  $tiers
     */
    public function __invoke(PriceCategory $category, array $tiers): void
    {
        $category->tiers()->delete();

        foreach ($tiers as $index => $tier) {
            $category->tiers()->create([
                'tier_number' => $tier['tier_number'],
                'label' => $tier['label'] ?? null,
                'multiplier' => $tier['multiplier'],
                'sort_order' => $tier['sort_order'] ?? $index,
            ]);
        }
    }
}
