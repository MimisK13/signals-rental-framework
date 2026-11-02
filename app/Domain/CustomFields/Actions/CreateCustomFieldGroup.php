<?php

declare(strict_types=1);

namespace App\Domain\CustomFields\Actions;

use App\Domain\CustomFields\Data\CreateCustomFieldGroupData;
use App\Domain\CustomFields\Data\CustomFieldGroupData;
use App\Domain\CustomFields\Models\CustomFieldGroup;
use App\Events\AuditableEvent;
use Illuminate\Support\Facades\Gate;

class CreateCustomFieldGroup
{
    public function __invoke(CreateCustomFieldGroupData $data): CustomFieldGroupData
    {
        Gate::authorize('custom-fields.manage');

        $group = CustomFieldGroup::create($data->toArray());

        event(new AuditableEvent($group, 'custom_field_group.created'));

        return CustomFieldGroupData::fromModel($group);
    }
}
