<?php

namespace App\Actions\Pricing;

use App\Events\AuditableEvent;
use App\Models\DiscountCategory;
use Illuminate\Support\Facades\Gate;

class DeleteDiscountCategory
{
    public function __invoke(DiscountCategory $category): void
    {
        Gate::authorize('delete', $category);

        event(new AuditableEvent($category, 'discount_category.deleted'));

        $category->delete();
    }
}
