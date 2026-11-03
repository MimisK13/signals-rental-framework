<?php

declare(strict_types=1);

namespace App\Domain\Projects\Opportunities\Actions;

use App\Domain\Projects\Opportunities\Models\OpportunityParticipant;
use App\Events\AuditableEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Detaches a member from an opportunity.
 *
 * Participants are plain, non-event-sourced rows, so this is a normal Eloquent
 * delete; an AuditableEvent is fired for audit parity (audit-only, like
 * RemoveOpportunityItem — not a registered webhook event).
 */
class RemoveOpportunityParticipant
{
    public function __invoke(OpportunityParticipant $participant): void
    {
        Gate::authorize('opportunities.edit');

        DB::transaction(function () use ($participant): void {
            event(new AuditableEvent(
                $participant,
                'opportunity.participant_removed',
                oldValues: [
                    'member_id' => $participant->member_id,
                    'role' => $participant->role,
                    'mute' => $participant->mute,
                ],
            ));

            $participant->delete();
        });
    }
}
