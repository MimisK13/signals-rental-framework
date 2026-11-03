<?php

declare(strict_types=1);

namespace App\Domain\Members\Actions;

use App\Domain\Members\Data\CreateLinkData;
use App\Domain\Members\Data\LinkData;
use App\Domain\Members\Models\Member;
use App\Domain\AuditLog\Events\AuditableEvent;
use Illuminate\Support\Facades\Gate;

class CreateLink
{
    public function __invoke(Member $member, CreateLinkData $data): LinkData
    {
        Gate::authorize('members.edit');

        $link = $member->links()->create($data->toArray());

        event(new AuditableEvent($member, 'member.link.created'));

        return LinkData::fromModel($link);
    }
}
