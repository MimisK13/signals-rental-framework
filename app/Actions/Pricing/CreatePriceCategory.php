<?php

namespace App\Actions\Pricing;

use App\Data\Pricing\CreatePriceCategoryData;
use App\Data\Pricing\PriceCategoryData;
use App\Events\AuditableEvent;
use App\Models\PriceCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CreatePriceCategory
{
    public function __invoke(CreatePriceCategoryData $data): PriceCategoryData
    {
        Gate::authorize('create', PriceCategory::class);

        return DB::transaction(function () use ($data): PriceCategoryData {
            $category = PriceCategory::query()->create([
                'name' => $data->name,
                'description' => $data->description,
                'is_active' => $data->is_active,
            ]);

            if ($data->tiers !== []) {
                (new SyncPriceCategoryTiers)($category, $data->tiers);
            }

            event(new AuditableEvent($category, 'price_category.created'));

            return PriceCategoryData::fromModel($category->load('tiers'));
        });
    }
}
