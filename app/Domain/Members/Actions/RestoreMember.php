<?php

declare(strict_types=1);

namespace App\Domain\Members\Actions;

use App\Domain\Members\Models\Member;
use App\Domain\AuditLog\Events\AuditableEvent;
use App\Domain\Webhooks\Services\WebhookService;
use Illuminate\Support\Facades\Gate;

class RestoreMember
{
    public function __invoke(Member $member): void
    {
        Gate::authorize('members.delete');

        if (! $member->trashed()) {
            return;
        }

        $member->restore();
        $member->update(['is_active' => true]);

        event(new AuditableEvent($member, 'member.restored'));

        app(WebhookService::class)->dispatch('member.restored', [
            'id' => $member->id,
        ]);
    }
}
