<?php

declare(strict_types=1);

namespace App\Domain\Lists\Actions;

use App\Domain\AuditLog\Events\AuditableEvent;
use App\Domain\Lists\Data\CreateListNameData;
use App\Domain\Lists\Data\ListNameData;
use App\Domain\Lists\Models\ListName;
use Illuminate\Support\Facades\Gate;

final class CreateListName
{
    public function __invoke(CreateListNameData $data): ListNameData
    {
        Gate::authorize('list-values.manage');

        $listName = ListName::create($data->toArray());

        event(new AuditableEvent($listName, 'list_name.created'));

        return ListNameData::fromModel($listName);
    }
}
