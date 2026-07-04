<?php

namespace App\Actions\Tax;

use App\Data\Tax\TaxCategoryData;
use App\Data\Tax\UpdateTaxCategoryData;
use App\Events\AuditableEvent;
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

        $oldValues = [];
        $newValues = [];

        foreach ($payload as $key => $value) {
            $old = $taxCategory->getAttribute($key);
            $oldValues[$key] = $old instanceof \BackedEnum ? $old->value : $old;
            $newValues[$key] = $value instanceof \BackedEnum ? $value->value : $value;
        }

        $taxCategory->update($payload);

        if ($payload !== []) {
            event(new AuditableEvent($taxCategory, 'tax_category.updated', $oldValues, $newValues));
        }

        return TaxCategoryData::fromModel($taxCategory->fresh());
    }
}
