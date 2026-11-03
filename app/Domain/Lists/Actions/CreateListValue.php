<?php

declare(strict_types=1);

namespace App\Domain\Lists\Actions;

use App\Domain\AuditLog\Events\AuditableEvent;
use App\Domain\Lists\Data\CreateListValueData;
use App\Domain\Lists\Data\ListValueData;
use App\Domain\Lists\Models\ListValue;
use App\Domain\Lists\Rules\EnsureListValueParentIsValid;
use Illuminate\Support\Facades\Gate;

final class CreateListValue
{
    public function __invoke(CreateListValueData $data): ListValueData
    {
        Gate::authorize('list-values.manage');

        (new EnsureListValueParentIsValid)($data->list_name_id, $data->parent_id);

        $value = ListValue::create($data->toArray());

        event(new AuditableEvent($value, 'list_value.created'));

        return ListValueData::fromModel($value);
    }
}
