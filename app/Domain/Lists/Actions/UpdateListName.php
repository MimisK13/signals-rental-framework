<?php

declare(strict_types=1);

namespace App\Domain\Lists\Actions;

use App\Domain\AuditLog\Events\AuditableEvent;
use App\Domain\Lists\Data\ListNameData;
use App\Domain\Lists\Data\UpdateListNameData;
use App\Domain\Lists\Models\ListName;
use Illuminate\Support\Facades\Gate;

final class UpdateListName
{
    public function __invoke(ListName $listName, UpdateListNameData $data): ListNameData
    {
        Gate::authorize('list-values.manage');

        $attributes = collect($data->toArray())
            ->except('list_name_id')
            ->reject(fn ($value) => $value === null)
            ->all();

        $listName->update($attributes);

        event(new AuditableEvent($listName, 'list_name.updated'));

        return ListNameData::fromModel($listName->fresh());
    }
}
