<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Actions;

use App\Domain\Pricing\Models\PriceCategory;
use App\Domain\AuditLog\Events\AuditableEvent;
use Illuminate\Support\Facades\Gate;

class DeletePriceCategory
{
    public function __invoke(PriceCategory $category): void
    {
        Gate::authorize('delete', $category);

        event(new AuditableEvent($category, 'price_category.deleted'));

        $category->delete();
    }
}
