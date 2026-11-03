<?php

declare(strict_types=1);

namespace App\Domain\Lists\Actions;

use App\Domain\AuditLog\Events\AuditableEvent;
use App\Domain\Lists\Models\ListName;
use Illuminate\Support\Facades\Gate;

final class DeleteListName
{
    public function __invoke(ListName $listName): void
    {
        Gate::authorize('list-values.manage');

        event(new AuditableEvent($listName, 'list_name.deleted'));

        $listName->delete();
    }
}
