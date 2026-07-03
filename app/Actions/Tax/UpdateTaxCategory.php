<?php

namespace App\Actions\Tax;

use App\Data\Tax\TaxCategoryData;
use App\Data\Tax\UpdateTaxCategoryData;
use App\Models\TaxCategory;
use Illuminate\Support\Facades\Gate;

class UpdateTaxCategory
{
    public function __invoke(TaxCategory $taxCategory, UpdateTaxCategoryData $data): TaxCategoryData
    {
        Gate::authorize('update', $taxCategory);

        if ($data->is_default === true) {
            TaxCategory::query()->whereKeyNot($taxCategory->id)->update(['is_default' => false]);
        }

        $payload = array_filter($data->toArray(), fn ($value): bool => $value !== null);
        $taxCategory->update($payload);

        return TaxCategoryData::fromModel($taxCategory->fresh());
    }
}
