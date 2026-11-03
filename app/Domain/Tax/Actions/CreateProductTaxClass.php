<?php

declare(strict_types=1);

namespace App\Domain\Tax\Actions;

use App\Domain\AuditLog\Events\AuditableEvent;
use App\Domain\Tax\Data\CreateTaxClassData;
use App\Domain\Tax\Data\TaxClassData;
use App\Domain\Tax\Models\ProductTaxClass;
use Illuminate\Support\Facades\Gate;

final class CreateProductTaxClass
{
    public function __invoke(CreateTaxClassData $data): TaxClassData
    {
        Gate::authorize('tax-classes.manage');

        $taxClass = ProductTaxClass::create($data->toArray());

        event(new AuditableEvent($taxClass, 'product_tax_class.created'));

        return TaxClassData::fromModel($taxClass);
    }
}
