<?php

declare(strict_types=1);

namespace App\Domain\Crew\Actions\Requests;

use App\Domain\Crew\Actions\Concerns\StaffingGuard;
use App\Domain\Crew\Data\Requests\CancelCrewRequestData;
use App\Domain\Crew\Data\Requests\CrewRequestData;
use App\Domain\Crew\Enums\CrewAssignmentStatus;
use App\Domain\Crew\Enums\CrewRequestStatus;
use App\Domain\Crew\Enums\CrewShiftStatus;
use App\Domain\Crew\Models\CrewRequest;
use App\Domain\Crew\Services\CrewAudit;
use App\Domain\Crew\Services\CrewCancellationCompensation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

final class CancelCrewRequest
{
    public function __invoke(CrewRequest $request, CancelCrewRequestData $data): CrewRequestData
    {
        Gate::authorize('cancel', $request);
        Validator::make($data->toArray(), ['reason' => ['required', 'string', 'max:2000']])->validate();

        return DB::transaction(function () use ($request, $data): CrewRequestData {
            $memberIds = $request->assignments()->whereIn('status', ['proposed', 'invited', 'confirmed'])->pluck('crew_member_id')->unique()->sort();
            foreach ($memberIds as $id) {
                StaffingGuard::memberLock($id);
            }
            $request = CrewRequest::query()->lockForUpdate()->findOrFail($request->id);
            StaffingGuard::require($request->status !== CrewRequestStatus::Cancelled, 'status', 'This request is already cancelled.');
            StaffingGuard::require(! $request->assignments()->whereHas('shifts', function ($query): void {
                $query->where('status', 'in_progress');
            })->exists(), 'shifts', 'Finish or cancel active shifts before cancelling this request.');
            foreach ($request->assignments()->whereIn('status', ['proposed', 'invited', 'confirmed'])->lockForUpdate()->get() as $assignment) {
                $cancellation = app(CrewCancellationCompensation::class)->record($assignment);
                $assignment->update(['status' => CrewAssignmentStatus::Cancelled, 'active_assignment_key' => null, 'cancelled_at' => now(), 'cancellation_reason' => $data->reason]);
                foreach ($assignment->shifts()->whereIn('status', ['planned', 'confirmed'])->get() as $shift) {
                    $shift->update(['status' => CrewShiftStatus::Cancelled]);
                    app(CrewAudit::class)->record('crew.shift.cancelled', $shift, ['reason' => $data->reason]);
                }
                app(CrewAudit::class)->record('crew.assignment.cancelled', $assignment, ['reason' => $data->reason, 'cancellation' => $cancellation]);
            }
            $request->lines()->update(['filled_quantity' => 0]);
            $request->update(['status' => CrewRequestStatus::Cancelled, 'cancelled_at' => now(), 'cancellation_reason' => $data->reason]);
            app(CrewAudit::class)->record('crew.request.cancelled', $request, ['reason' => $data->reason]);

            return CrewRequestData::fromModel($request);
        });
    }
}
