<?php

namespace App\Actions\Pricing;

use App\Models\SurchargeGroup;

class SyncSurchargeGroupSurcharges
{
    /**
     * @param  list<array<string, mixed>>  $surcharges
     */
    public function __invoke(SurchargeGroup $group, array $surcharges): void
    {
        $group->surcharges()->delete();

        foreach ($surcharges as $index => $surcharge) {
            $percent = $surcharge['percent_value'] ?? null;
            $fixed = $surcharge['fixed_value_minor'] ?? null;

            $hasPercent = $percent !== null && $percent !== '';
            $hasFixed = $fixed !== null && $fixed !== '';

            if ($hasPercent === $hasFixed) {
                throw new \InvalidArgumentException(sprintf(
                    'Surcharge at index %d must specify exactly one of percent_value or fixed_value_minor.',
                    $index,
                ));
            }

            $group->surcharges()->create([
                'name' => $surcharge['name'],
                'percent_value' => $hasPercent ? $percent : null,
                'fixed_value_minor' => $hasFixed ? (int) $fixed : null,
                'criteria' => $surcharge['criteria'] ?? [],
                'is_default' => $surcharge['is_default'] ?? false,
                'product_tax_class_id' => $surcharge['product_tax_class_id'] ?? null,
                'valid_from' => $surcharge['valid_from'] ?? null,
                'valid_to' => $surcharge['valid_to'] ?? null,
                'sort_order' => $surcharge['sort_order'] ?? $index,
            ]);
        }
    }
}
