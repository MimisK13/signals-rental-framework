<?php

namespace App\Actions\Tax;

use App\Data\Tax\CreateTaxCategoryData;
use App\Data\Tax\TaxCategoryData;
use App\Events\AuditableEvent;
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

        event(new AuditableEvent(
            $taxCategory,
            'tax_category.created',
            null,
            $this->auditSnapshot($taxCategory),
        ));

        return TaxCategoryData::fromModel($taxCategory);
    }

    /**
     * @return array<string, mixed>
     */
    private function auditSnapshot(TaxCategory $taxCategory): array
    {
        return [
            'name' => $taxCategory->name,
            'slug' => $taxCategory->slug,
            'type' => $this->enumValue($taxCategory->type),
            'description' => $taxCategory->description,
            'is_default' => $taxCategory->is_default,
            'is_active' => $taxCategory->is_active,
        ];
    }

    private function enumValue(mixed $value): mixed
    {
        return $value instanceof \BackedEnum ? $value->value : $value;
    }
}
