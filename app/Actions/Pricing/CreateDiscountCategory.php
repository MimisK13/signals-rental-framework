<?php

namespace App\Actions\Pricing;

use App\Data\Pricing\CreateDiscountCategoryData;
use App\Data\Pricing\DiscountCategoryData;
use App\Events\AuditableEvent;
use App\Models\DiscountCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CreateDiscountCategory
{
    public function __invoke(CreateDiscountCategoryData $data): DiscountCategoryData
    {
        Gate::authorize('create', DiscountCategory::class);

        return DB::transaction(function () use ($data): DiscountCategoryData {
            $category = DiscountCategory::query()->create([
                'name' => $data->name,
                'description' => $data->description,
                'is_active' => $data->is_active,
            ]);

            if ($data->rules !== []) {
                (new SyncDiscountCategoryRules)($category, $data->rules);
            }

            event(new AuditableEvent($category, 'discount_category.created'));

            return DiscountCategoryData::fromModel($category->load('rules'));
        });
    }
}
