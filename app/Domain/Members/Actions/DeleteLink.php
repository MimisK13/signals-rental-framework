<?php

declare(strict_types=1);

namespace App\Domain\Members\Actions;

use App\Domain\Members\Models\Link;
use App\Domain\AuditLog\Events\AuditableEvent;
use Illuminate\Support\Facades\Gate;

class DeleteLink
{
    public function __invoke(Link $link): void
    {
        Gate::authorize('members.edit');

        event(new AuditableEvent($link, 'member.link.deleted'));

        $link->delete();
    }
}
