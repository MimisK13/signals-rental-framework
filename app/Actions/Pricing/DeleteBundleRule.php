<?php

namespace App\Actions\Pricing;

use App\Events\AuditableEvent;
use App\Models\BundleRule;
use Illuminate\Support\Facades\Gate;

class DeleteBundleRule
{
    public function __invoke(BundleRule $rule): void
    {
        Gate::authorize('delete', $rule);

        $rule->delete();

        event(new AuditableEvent($rule, 'bundle_rule.deleted'));
    }
}
