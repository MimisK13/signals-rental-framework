<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Actions;

use App\Domain\Pricing\Models\DiscountRule;
use App\Domain\AuditLog\Events\AuditableEvent;
use Illuminate\Support\Facades\Gate;

class DeleteDiscountRule
{
    public function __invoke(DiscountRule $rule): void
    {
        Gate::authorize('delete', $rule);

        event(new AuditableEvent($rule, 'discount_rule.deleted'));

        $rule->delete();
    }
}
