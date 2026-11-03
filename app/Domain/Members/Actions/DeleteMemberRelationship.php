<?php

declare(strict_types=1);

namespace App\Domain\Members\Actions;

use App\Domain\Members\Models\MemberRelationship;
use App\Domain\AuditLog\Events\AuditableEvent;
use Illuminate\Support\Facades\Gate;

class DeleteMemberRelationship
{
    public function __invoke(MemberRelationship $relationship): void
    {
        Gate::authorize('members.edit');

        event(new AuditableEvent($relationship, 'member.relationship.deleted'));

        $relationship->delete();
    }
}
