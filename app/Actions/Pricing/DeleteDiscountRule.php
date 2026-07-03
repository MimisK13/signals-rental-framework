<?php

namespace App\Actions\Pricing;

use App\Events\AuditableEvent;
use App\Models\DiscountRule;
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
