<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Actions;

use App\Domain\Pricing\Data\CreatePriceCategoryData;
use App\Domain\Pricing\Data\PriceCategoryData;
use App\Domain\Pricing\Models\PriceCategory;
use App\Domain\AuditLog\Events\AuditableEvent;
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
