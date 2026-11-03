<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Actions;

use App\Domain\Pricing\Models\BundleRule;
use App\Domain\AuditLog\Events\AuditableEvent;
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
