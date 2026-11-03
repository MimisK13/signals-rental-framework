<?php

declare(strict_types=1);

namespace App\Domain\Members\Actions;

use App\Domain\Members\Data\LinkData;
use App\Domain\Members\Data\UpdateLinkData;
use App\Domain\Members\Models\Link;
use App\Domain\AuditLog\Events\AuditableEvent;
use Illuminate\Support\Facades\Gate;

class UpdateLink
{
    public function __invoke(Link $link, UpdateLinkData $data): LinkData
    {
        Gate::authorize('members.edit');

        $link->update(array_filter($data->toArray(), fn ($v) => $v !== null));

        event(new AuditableEvent($link, 'member.link.updated'));

        return LinkData::fromModel($link->fresh());
    }
}
