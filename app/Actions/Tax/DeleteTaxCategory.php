<?php

namespace App\Actions\Tax;

use App\Events\AuditableEvent;
use App\Models\TaxCategory;
use Illuminate\Support\Facades\Gate;

class DeleteTaxCategory
{
    public function __invoke(TaxCategory $taxCategory): void
    {
        Gate::authorize('delete', $taxCategory);

        event(new AuditableEvent($taxCategory, 'tax_category.deleted', [
            'name' => $taxCategory->name,
            'slug' => $taxCategory->slug,
            'type' => $this->backedEnumValue($taxCategory->type),
            'description' => $taxCategory->description,
            'is_default' => $taxCategory->is_default,
            'is_active' => $taxCategory->is_active,
        ]));

        $taxCategory->delete();
    }

    private function backedEnumValue(mixed $value): mixed
    {
        return $value instanceof \BackedEnum ? $value->value : $value;
    }
}
