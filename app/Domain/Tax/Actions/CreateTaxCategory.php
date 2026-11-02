<?php

declare(strict_types=1);

namespace App\Domain\Tax\Actions;

use App\Domain\Tax\Data\CreateTaxCategoryData;
use App\Domain\Tax\Data\TaxCategoryData;
use App\Domain\Tax\Models\TaxCategory;
use App\Events\AuditableEvent;
use Illuminate\Support\Facades\Gate;

final class CreateTaxCategory
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
