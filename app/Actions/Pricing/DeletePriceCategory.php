<?php

namespace App\Actions\Pricing;

use App\Events\AuditableEvent;
use App\Models\PriceCategory;
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
