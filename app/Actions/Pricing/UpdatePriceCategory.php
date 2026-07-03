<?php

namespace App\Actions\Pricing;

use App\Data\Pricing\PriceCategoryData;
use App\Data\Pricing\UpdatePriceCategoryData;
use App\Events\AuditableEvent;
use App\Models\PriceCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class UpdatePriceCategory
{
    public function __invoke(PriceCategory $category, UpdatePriceCategoryData $data): PriceCategoryData
    {
        Gate::authorize('update', $category);

        return DB::transaction(function () use ($category, $data): PriceCategoryData {
            $attributes = array_filter([
                'name' => $data->name,
                'description' => $data->description,
                'is_active' => $data->is_active,
            ], fn (mixed $value): bool => $value !== null);

            if ($attributes !== []) {
                $category->update($attributes);
            }

            if ($data->tiers !== null) {
                (new SyncPriceCategoryTiers)($category, $data->tiers);
            }

            event(new AuditableEvent($category, 'price_category.updated'));

            return PriceCategoryData::fromModel($category->refresh()->load('tiers'));
        });
    }
}
