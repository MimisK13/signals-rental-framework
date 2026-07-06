<?php

namespace App\Actions\Pricing;

use App\Data\Pricing\DiscountCategoryData;
use App\Data\Pricing\UpdateDiscountCategoryData;
use App\Events\AuditableEvent;
use App\Models\DiscountCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class UpdateDiscountCategory
{
    public function __invoke(DiscountCategory $category, UpdateDiscountCategoryData $data): DiscountCategoryData
    {
        Gate::authorize('update', $category);

        return DB::transaction(function () use ($category, $data): DiscountCategoryData {
            $attributes = array_filter([
                'name' => $data->name,
                'description' => $data->description,
                'is_active' => $data->is_active,
            ], fn (mixed $value): bool => $value !== null);

            if ($attributes !== []) {
                $category->update($attributes);
            }

            if ($data->rules !== null) {
                (new SyncDiscountCategoryRules)($category, $data->rules);
            }

            event(new AuditableEvent($category, 'discount_category.updated'));

            return DiscountCategoryData::fromModel($category->refresh()->load('rules'));
        });
    }
}
