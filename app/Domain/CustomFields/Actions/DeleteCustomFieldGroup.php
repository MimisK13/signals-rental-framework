<?php

declare(strict_types=1);

namespace App\Domain\CustomFields\Actions;

use App\Domain\AuditLog\Events\AuditableEvent;
use App\Domain\CustomFields\Models\CustomFieldGroup;
use Illuminate\Support\Facades\Gate;

class DeleteCustomFieldGroup
{
    public function __invoke(CustomFieldGroup $group): void
    {
        Gate::authorize('custom-fields.manage');

        event(new AuditableEvent($group, 'custom_field_group.deleted'));

        $group->delete();
    }
}
