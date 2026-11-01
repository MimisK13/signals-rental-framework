<?php

declare(strict_types=1);

namespace App\Domain\Crew\Console;

use App\Domain\Crew\Models\CrewAssignment;
use App\Domain\Crew\Models\CrewMember;
use App\Domain\Crew\Models\CrewRequest;
use App\Domain\Crew\Services\CrewAudit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class ExpireCrewInvitations extends Command
{
    protected $signature = 'crew:expire-invitations';

    protected $description = 'Cancel unanswered Crew invitations after the configured response period';

    public function handle(): int
    {
        if (! settings()->moduleEnabled('crew')) {
            return self::SUCCESS;
        }
        $cutoff = now()->subHours((int) settings('crew.invite_expiry_hours', 72));
        $count = 0;

        foreach (CrewAssignment::query()->where('status', 'invited')->where('invited_at', '<=', $cutoff)->lazyById(100) as $candidate) {
            $changed = DB::transaction(function () use ($candidate, $cutoff): bool {
                CrewMember::query()->lockForUpdate()->findOrFail($candidate->crew_member_id);
                CrewRequest::query()->lockForUpdate()->findOrFail($candidate->crew_request_id);
                $assignment = CrewAssignment::query()->lockForUpdate()->findOrFail($candidate->id);
                if ($assignment->status->value !== 'invited' || $assignment->invited_at->gt($cutoff)) {
                    return false;
                }
                if ($assignment->shifts()->whereIn('status', ['in_progress', 'completed'])->exists()) {
                    return false;
                }
                $assignment->update(['status' => 'cancelled', 'active_assignment_key' => null, 'cancelled_at' => now(), 'cancellation_reason' => 'Invitation expired without a response.']);
                foreach ($assignment->shifts()->whereIn('status', ['planned', 'confirmed'])->get() as $shift) {
                    $shift->update(['status' => 'cancelled']);
                    CrewAudit::record('crew.shift.cancelled', $shift, ['reason' => 'Invitation expired.']);
                }
                CrewAudit::record('crew.assignment.cancelled', $assignment, ['reason' => 'Invitation expired.']);

                return true;
            });
            if ($changed) {
                $count++;
            }
        }
        $this->info('Expired '.$count.' crew invitations.');

        return self::SUCCESS;
    }
}
