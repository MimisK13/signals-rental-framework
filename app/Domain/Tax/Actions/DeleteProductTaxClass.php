<?php

declare(strict_types=1);

namespace App\Domain\Tax\Actions;

use App\Domain\AuditLog\Events\AuditableEvent;
use App\Domain\Tax\Models\ProductTaxClass;
use Illuminate\Support\Facades\Gate;

final class DeleteProductTaxClass
{
    public function __invoke(ProductTaxClass $taxClass): void
    {
        Gate::authorize('tax-classes.manage');

        event(new AuditableEvent($taxClass, 'product_tax_class.deleted'));

        $taxClass->delete();
    }
}
