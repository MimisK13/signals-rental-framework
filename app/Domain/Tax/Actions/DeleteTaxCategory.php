<?php

declare(strict_types=1);

namespace App\Domain\Tax\Actions;

use App\Domain\AuditLog\Events\AuditableEvent;
use App\Domain\Tax\Models\TaxCategory;
use Illuminate\Support\Facades\Gate;

final class DeleteTaxCategory
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
