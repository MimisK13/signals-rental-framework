<?php

declare(strict_types=1);

namespace App\Domain\Crew\Actions\Availability;

use App\Domain\Crew\Actions\Concerns\StaffingGuard;
use App\Domain\Crew\Data\Availability\CrewAvailabilityData;
use App\Domain\Crew\Enums\CrewAvailabilityStatus;
use App\Domain\Crew\Models\CrewAvailability;
use App\Domain\Crew\Queries\CrewConflictQuery;
use App\Domain\Crew\Services\CrewAudit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class ApproveCrewAvailability
{
    public function __invoke(CrewAvailability $availability): CrewAvailabilityData
    {
        Gate::authorize('approve', $availability);

        return DB::transaction(function () use ($availability): CrewAvailabilityData {
            StaffingGuard::memberLock($availability->crew_member_id);
            $availability = CrewAvailability::query()->lockForUpdate()->findOrFail($availability->id);
            StaffingGuard::require($availability->status->canTransitionTo(CrewAvailabilityStatus::Approved), 'status', 'This availability cannot make the requested transition.');
            $report = app(CrewConflictQuery::class)->detect($availability->crew_member_id, CarbonImmutable::parse($availability->starts_at), CarbonImmutable::parse($availability->ends_at));
            $blocking = array_filter($report->hard, function (array $conflict): bool {
                return in_array($conflict['code'], ['SHIFT_OVERLAP_CONFIRMED', 'DOUBLE_BOOKED_PROJECT'], true);
            });
            StaffingGuard::require($blocking === [], 'starts_at', 'Approved work overlaps this availability. Cancel or reassign that work first.');
            $availability->update(['status' => CrewAvailabilityStatus::Approved, 'approved_at' => now(), 'approved_by' => auth()->id()]);
            app(CrewAudit::class)->record('crew.availability.approved', $availability);

            return CrewAvailabilityData::fromModel($availability);
        });
    }
}
