<?php

declare(strict_types=1);

namespace App\Domain\Crew\Actions\Assignments;

use App\Domain\Crew\Actions\Concerns\StaffingGuard;
use App\Domain\Crew\Data\Assignments\CrewAssignmentData;
use App\Domain\Crew\Enums\CrewAssignmentStatus;
use App\Domain\Crew\Models\CrewAssignment;
use App\Domain\Crew\Services\CrewAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class InviteCrewAssignment
{
    public function __invoke(CrewAssignment $assignment): CrewAssignmentData
    {
        Gate::authorize('crew.assignments.create');
        Gate::allowIf(auth()->user()->canAccessStore($assignment->store_id));

        return DB::transaction(function () use ($assignment): CrewAssignmentData {
            $assignment = CrewAssignment::query()->lockForUpdate()->findOrFail($assignment->id);
            StaffingGuard::require($assignment->status === CrewAssignmentStatus::Proposed, 'status', 'Only proposed assignments can be invited.');
            $assignment->update(['status' => CrewAssignmentStatus::Invited, 'invited_at' => now()]);
            app(CrewAudit::class)->record('crew.assignment.invited', $assignment);

            return CrewAssignmentData::fromModel($assignment);
        });
    }
}
