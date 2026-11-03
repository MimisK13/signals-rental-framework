<?php

declare(strict_types=1);

namespace App\Domain\Members\Actions;

use App\Domain\Members\Models\Member;
use App\Domain\AuditLog\Events\AuditableEvent;
use App\Domain\Webhooks\Services\WebhookService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ArchiveMember
{
    public function __invoke(Member $member): void
    {
        Gate::authorize('members.delete');

        DB::transaction(function () use ($member): void {
            $member->update(['is_active' => false]);

            event(new AuditableEvent($member, 'member.archived'));

            $member->delete();
        });

        // DeliverWebhook sets afterCommit = true, so each delivery only enqueues
        // after the surrounding transaction commits (and is dropped on rollback).
        // Dispatch placement relative to the transaction is therefore safe either way.
        app(WebhookService::class)->dispatch('member.archived', [
            'id' => $member->id,
        ]);
    }
}
