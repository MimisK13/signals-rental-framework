<?php

declare(strict_types=1);

namespace App\Domain\Crew\Actions\Shifts;

use App\Domain\Crew\Actions\Concerns\StaffingGuard;
use App\Domain\Crew\Data\Shifts\CrewShiftData;
use App\Domain\Crew\Enums\CrewAssignmentStatus;
use App\Domain\Crew\Enums\CrewShiftStatus;
use App\Domain\Crew\Models\CrewAssignment;
use App\Domain\Crew\Models\CrewShift;
use App\Domain\Crew\Queries\CrewConflictQuery;
use App\Domain\Crew\Queries\CrewRequirementQuery;
use App\Domain\Crew\Services\CrewAudit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class ConfirmCrewShift
{
    public function __invoke(CrewShift $shift, ?string $overrideReason = null): CrewShiftData
    {
        Gate::authorize('confirm', $shift);

        return DB::transaction(function () use ($shift, $overrideReason): CrewShiftData {
            StaffingGuard::require($shift->crew_member_id !== null && $shift->crew_assignment_id !== null, 'crew_member_id', 'An assigned crew member is required.');
            StaffingGuard::memberLock($shift->crew_member_id);
            $assignment = CrewAssignment::query()->lockForUpdate()->findOrFail($shift->crew_assignment_id);
            $shift = CrewShift::query()->lockForUpdate()->findOrFail($shift->id);
            StaffingGuard::require($shift->status->canTransitionTo(CrewShiftStatus::Confirmed), 'status', 'This shift cannot make the requested transition.');
            StaffingGuard::require($assignment->status === CrewAssignmentStatus::Confirmed, 'crew_assignment_id', 'Confirm the assignment before confirming shifts.');
            StaffingGuard::require(! $assignment->shifts()->whereKeyNot($shift->id)->where('status', '!=', 'cancelled')->where('starts_at', '<', $shift->ends_at)->where('ends_at', '>', $shift->starts_at)->exists(), 'starts_at', 'Shifts in the same assignment cannot overlap.');
            $report = app(CrewConflictQuery::class)->detect($shift->crew_member_id, CarbonImmutable::parse($shift->starts_at), CarbonImmutable::parse($shift->ends_at), $shift->id, $assignment->id);
            $requirements = app(CrewRequirementQuery::class)->forLine($assignment->crewMember, $assignment->line);
            $report->hard = [...$report->hard, ...$requirements->hard];
            $report->warnings = [...$report->warnings, ...$requirements->warnings];
            StaffingGuard::conflicts($report, $overrideReason);
            $shift->update(['status' => CrewShiftStatus::Confirmed]);
            app(CrewAudit::class)->record('crew.shift.confirmed', $shift, ['override_reason' => $overrideReason, 'warnings' => $report->warnings]);

            return CrewShiftData::fromModel($shift);
        });
    }
}
