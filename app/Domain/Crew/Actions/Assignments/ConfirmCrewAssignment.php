<?php

declare(strict_types=1);

namespace App\Domain\Crew\Actions\Assignments;

use App\Domain\Crew\Actions\Concerns\StaffingGuard;
use App\Domain\Crew\Data\Assignments\CrewAssignmentData;
use App\Domain\Crew\Enums\CrewAssignmentStatus;
use App\Domain\Crew\Enums\CrewRequestStatus;
use App\Domain\Crew\Models\CrewAssignment;
use App\Domain\Crew\Models\CrewRequest;
use App\Domain\Crew\Models\CrewRequestLine;
use App\Domain\Crew\Queries\CrewConflictQuery;
use App\Domain\Crew\Queries\CrewRequirementQuery;
use App\Domain\Crew\Services\CrewAudit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class ConfirmCrewAssignment
{
    public function __invoke(CrewAssignment $assignment, ?string $overrideReason = null): CrewAssignmentData
    {
        Gate::authorize('confirm', $assignment);

        return DB::transaction(function () use ($assignment, $overrideReason): CrewAssignmentData {
            $member = StaffingGuard::memberLock($assignment->crew_member_id);
            $request = CrewRequest::query()->lockForUpdate()->findOrFail($assignment->crew_request_id);
            $line = CrewRequestLine::query()->lockForUpdate()->findOrFail($assignment->crew_request_line_id);
            $assignment = CrewAssignment::query()->lockForUpdate()->findOrFail($assignment->id);
            StaffingGuard::require($assignment->status === CrewAssignmentStatus::Invited, 'status', 'Only invited assignments may be confirmed.');
            StaffingGuard::require(in_array($request->status, [CrewRequestStatus::Open, CrewRequestStatus::PartiallyFilled], true), 'status', 'The request is no longer open.');
            StaffingGuard::require($line->assignments()->where('status', 'confirmed')->count() < $line->quantity, 'quantity', 'The role line is already filled.');
            StaffingGuard::require($request->store_id === $assignment->store_id && $request->project_id === $assignment->project_id, 'store_id', 'The assignment must match its request.');
            $requirements = app(CrewRequirementQuery::class)->forLine($member, $line);
            $shifts = $assignment->shifts()->where('status', '!=', 'cancelled')->orderBy('starts_at')->get();
            $report = app(CrewConflictQuery::class)->detect($member->id, CarbonImmutable::parse($line->effectiveStartsAt()), CarbonImmutable::parse($line->effectiveEndsAt()), ignoreAssignmentId: $assignment->id);
            foreach ($shifts as $index => $shift) {
                if ($index > 0) {
                    StaffingGuard::require($shifts[$index - 1]->ends_at->lte($shift->starts_at), 'starts_at', 'Shifts within the assignment cannot overlap.');
                }
                $check = app(CrewConflictQuery::class)->detect($member->id, CarbonImmutable::parse($shift->starts_at), CarbonImmutable::parse($shift->ends_at), ignoreAssignmentId: $assignment->id);
                $report->hard = [...$report->hard, ...$check->hard];
                $report->warnings = [...$report->warnings, ...$check->warnings];
            }
            $report->hard = [...$report->hard, ...$requirements->hard];
            $report->warnings = [...$report->warnings, ...$requirements->warnings];
            StaffingGuard::conflicts($report, $overrideReason);
            $assignment->update(['status' => CrewAssignmentStatus::Confirmed, 'responded_at' => now(), 'confirmed_at' => now(), 'agreed_at' => now(), 'agreed_by' => auth()->id()]);
            $member->update(['last_assigned_at' => now()]);
            $line->update(['filled_quantity' => $line->assignments()->where('status', 'confirmed')->count()]);
            $lines = $request->lines()->get();
            $status = $lines->sum('filled_quantity') >= $lines->sum('quantity') ? CrewRequestStatus::Filled : CrewRequestStatus::PartiallyFilled;
            $request->update(['status' => $status]);
            app(CrewAudit::class)->record('crew.assignment.confirmed', $assignment, ['override_reason' => $overrideReason, 'warnings' => $report->warnings]);
            if ($status === CrewRequestStatus::Filled) {
                app(CrewAudit::class)->record('crew.request.filled', $request);
            }

            return CrewAssignmentData::fromModel($assignment);
        });
    }
}
