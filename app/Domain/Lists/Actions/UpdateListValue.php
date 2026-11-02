<?php

declare(strict_types=1);

namespace App\Domain\Lists\Actions;

use App\Domain\Lists\Data\ListValueData;
use App\Domain\Lists\Data\UpdateListValueData;
use App\Domain\Lists\Models\ListValue;
use App\Domain\Lists\Rules\EnsureListValueParentIsValid;
use App\Events\AuditableEvent;
use Illuminate\Support\Facades\Gate;

final class UpdateListValue
{
    public function __invoke(ListValue $value, UpdateListValueData $data): ListValueData
    {
        Gate::authorize('list-values.manage');

        $attributes = $data->toArray();

        if (array_key_exists('parent_id', $attributes)) {
            (new EnsureListValueParentIsValid)(
                $value->list_name_id,
                $attributes['parent_id'],
                $value->id,
            );
        }

        $value->update($attributes);

        event(new AuditableEvent($value, 'list_value.updated'));

        return ListValueData::fromModel($value->fresh());
    }
}
