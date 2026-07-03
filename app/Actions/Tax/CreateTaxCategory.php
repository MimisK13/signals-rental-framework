<?php

namespace App\Actions\Tax;

use App\Data\Tax\CreateTaxCategoryData;
use App\Data\Tax\TaxCategoryData;
use App\Models\TaxCategory;
use Illuminate\Support\Facades\Gate;

class CreateTaxCategory
{
    public function __invoke(CreateTaxCategoryData $data): TaxCategoryData
    {
        Gate::authorize('create', TaxCategory::class);

        if ($data->is_default) {
            TaxCategory::query()->update(['is_default' => false]);
        }

        $taxCategory = TaxCategory::create($data->toArray());

        return TaxCategoryData::fromModel($taxCategory);
    }
}
