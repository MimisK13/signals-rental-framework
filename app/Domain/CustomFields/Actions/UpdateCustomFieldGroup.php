<?php

declare(strict_types=1);

namespace App\Domain\CustomFields\Actions;

use App\Domain\CustomFields\Data\CustomFieldGroupData;
use App\Domain\CustomFields\Data\UpdateCustomFieldGroupData;
use App\Domain\CustomFields\Models\CustomFieldGroup;
use App\Events\AuditableEvent;
use Illuminate\Support\Facades\Gate;

class UpdateCustomFieldGroup
{
    public function __invoke(CustomFieldGroup $group, UpdateCustomFieldGroupData $data): CustomFieldGroupData
    {
        Gate::authorize('custom-fields.manage');

        $group->update(array_filter($data->toArray(), fn ($v) => $v !== null));

        event(new AuditableEvent($group, 'custom_field_group.updated'));

        return CustomFieldGroupData::fromModel($group->fresh());
    }
}
