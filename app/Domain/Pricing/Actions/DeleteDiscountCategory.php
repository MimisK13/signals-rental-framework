<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Actions;

use App\Domain\Pricing\Models\DiscountCategory;
use App\Domain\AuditLog\Events\AuditableEvent;
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
