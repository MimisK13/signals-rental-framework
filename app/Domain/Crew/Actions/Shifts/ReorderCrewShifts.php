<?php

declare(strict_types=1);

namespace App\Domain\Crew\Actions\Shifts;

use App\Domain\Crew\Actions\Concerns\StaffingGuard;
use App\Domain\Crew\Data\Shifts\CrewShiftData;
use App\Domain\Crew\Data\Shifts\ReorderCrewShiftsData;
use App\Domain\Crew\Models\CrewAssignment;
use App\Domain\Crew\Services\CrewAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

final class ReorderCrewShifts
{
    /** @return list<CrewShiftData> */
    public function __invoke(CrewAssignment $assignment, ReorderCrewShiftsData $data): array
    {
        Gate::authorize('crew.shifts.manage');
        Gate::allowIf(auth()->user()->canAccessStore($assignment->store_id));
        Validator::make($data->toArray(), ['shift_ids' => ['array'], 'shift_ids.*' => ['integer', 'distinct']])->validate();

        return DB::transaction(function () use ($assignment, $data): array {
            StaffingGuard::memberLock($assignment->crew_member_id);
            $assignment = CrewAssignment::query()->lockForUpdate()->findOrFail($assignment->id);
            $shifts = $assignment->shifts()->lockForUpdate()->get()->keyBy('id');
            $expected = $shifts->keys()->sort()->values()->all();
            $received = $data->shift_ids;
            sort($received);
            StaffingGuard::require($expected === $received, 'shift_ids', 'Provide every shift of this assignment exactly once.');
            $result = [];
            foreach ($data->shift_ids as $index => $id) {
                $shift = $shifts[$id];
                $shift->update(['sequence' => $index]);
                $result[] = CrewShiftData::fromModel($shift);
            }
            app(CrewAudit::class)->record('crew.assignment.shifts_reordered', $assignment);

            return $result;
        });
    }
}
