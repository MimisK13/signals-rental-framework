<?php

declare(strict_types=1);

namespace App\Domain\Members\Actions;

use App\Domain\Members\Models\Member;
use App\Domain\AuditLog\Events\AuditableEvent;
use App\Domain\Webhooks\Services\WebhookService;
use Illuminate\Support\Facades\Gate;

class DeleteMember
{
    public function __invoke(Member $member): void
    {
        Gate::authorize('members.delete');

        event(new AuditableEvent($member, 'member.deleted'));

        app(WebhookService::class)->dispatch('member.deleted', [
            'id' => $member->id,
        ]);

        $member->delete();
    }
}
