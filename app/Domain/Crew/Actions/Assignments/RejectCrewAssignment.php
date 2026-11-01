<?php

declare(strict_types=1);

namespace App\Domain\Crew\Actions\Assignments;

use App\Domain\Crew\Actions\Concerns\StaffingGuard;
use App\Domain\Crew\Data\Assignments\CrewAssignmentData;
use App\Domain\Crew\Data\Assignments\RejectCrewAssignmentData;
use App\Domain\Crew\Enums\CrewAssignmentStatus;
use App\Domain\Crew\Enums\CrewRequestStatus;
use App\Domain\Crew\Enums\CrewShiftStatus;
use App\Domain\Crew\Models\CrewAssignment;
use App\Domain\Crew\Models\CrewRequest;
use App\Domain\Crew\Models\CrewRequestLine;
use App\Domain\Crew\Services\CrewAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

final class RejectCrewAssignment
{
    public function __invoke(CrewAssignment $assignment, RejectCrewAssignmentData $data): CrewAssignmentData
    {
        Gate::authorize('reject', $assignment);
        Validator::make($data->toArray(), ['reason' => ['required', 'string', 'max:2000']])->validate();

        return DB::transaction(function () use ($assignment, $data): CrewAssignmentData {
            StaffingGuard::memberLock($assignment->crew_member_id);
            $request = CrewRequest::query()->lockForUpdate()->findOrFail($assignment->crew_request_id);
            $line = CrewRequestLine::query()->lockForUpdate()->findOrFail($assignment->crew_request_line_id);
            $assignment = CrewAssignment::query()->lockForUpdate()->findOrFail($assignment->id);
            StaffingGuard::require($assignment->status->canTransitionTo(CrewAssignmentStatus::Rejected), 'status', 'This assignment cannot make the requested transition.');
            $assignment->update(['status' => CrewAssignmentStatus::Rejected, 'active_assignment_key' => null, 'cancellation_reason' => $data->reason, 'cancelled_at' => now(), 'responded_at' => now()]);
            foreach ($assignment->shifts()->whereIn('status', ['planned', 'confirmed'])->get() as $shift) {
                $shift->update(['status' => CrewShiftStatus::Cancelled]);
                app(CrewAudit::class)->record('crew.shift.cancelled', $shift, ['reason' => $data->reason]);
            }
            $line->update(['filled_quantity' => $line->assignments()->where('status', 'confirmed')->count()]);
            if (! in_array($request->status, [CrewRequestStatus::Draft, CrewRequestStatus::Cancelled], true)) {
                $lines = $request->lines()->get();
                $filled = $lines->sum('filled_quantity');
                $request->update(['status' => $filled === 0 ? CrewRequestStatus::Open : ($filled >= $lines->sum('quantity') ? CrewRequestStatus::Filled : CrewRequestStatus::PartiallyFilled)]);
            }
            app(CrewAudit::class)->record('crew.assignment.rejected', $assignment, ['reason' => $data->reason]);

            return CrewAssignmentData::fromModel($assignment);
        });
    }
}
