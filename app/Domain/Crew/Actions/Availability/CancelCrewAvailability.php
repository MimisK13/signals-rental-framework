<?php

declare(strict_types=1);

namespace App\Domain\Crew\Actions\Availability;

use App\Domain\Crew\Actions\Concerns\StaffingGuard;
use App\Domain\Crew\Data\Availability\CrewAvailabilityData;
use App\Domain\Crew\Enums\CrewAvailabilityStatus;
use App\Domain\Crew\Models\CrewAvailability;
use App\Domain\Crew\Services\CrewAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class CancelCrewAvailability
{
    public function __invoke(CrewAvailability $availability): CrewAvailabilityData
    {
        Gate::authorize('cancel', $availability);

        return DB::transaction(function () use ($availability): CrewAvailabilityData {
            StaffingGuard::memberLock($availability->crew_member_id);
            $availability = CrewAvailability::query()->lockForUpdate()->findOrFail($availability->id);
            StaffingGuard::require($availability->status->canTransitionTo(CrewAvailabilityStatus::Cancelled), 'status', 'This availability cannot make the requested transition.');

            $availability->update(['status' => CrewAvailabilityStatus::Cancelled]);
            app(CrewAudit::class)->record('crew.availability.cancelled', $availability);

            return CrewAvailabilityData::fromModel($availability);
        });
    }
}
