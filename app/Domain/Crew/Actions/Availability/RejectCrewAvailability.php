<?php

declare(strict_types=1);

namespace App\Domain\Crew\Actions\Availability;

use App\Domain\Crew\Actions\Concerns\StaffingGuard;
use App\Domain\Crew\Data\Availability\CrewAvailabilityData;
use App\Domain\Crew\Data\Availability\RejectCrewAvailabilityData;
use App\Domain\Crew\Enums\CrewAvailabilityStatus;
use App\Domain\Crew\Models\CrewAvailability;
use App\Domain\Crew\Services\CrewAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

final class RejectCrewAvailability
{
    public function __invoke(CrewAvailability $availability, RejectCrewAvailabilityData $data): CrewAvailabilityData
    {
        Gate::authorize('reject', $availability);
        Validator::make($data->toArray(), ['reason' => ['required', 'string', 'max:2000']])->validate();

        return DB::transaction(function () use ($availability, $data): CrewAvailabilityData {
            StaffingGuard::memberLock($availability->crew_member_id);
            $availability = CrewAvailability::query()->lockForUpdate()->findOrFail($availability->id);
            StaffingGuard::require($availability->status->canTransitionTo(CrewAvailabilityStatus::Rejected), 'status', 'This availability cannot make the requested transition.');

            $availability->update(['status' => CrewAvailabilityStatus::Rejected, 'notes' => $data->reason]);
            app(CrewAudit::class)->record('crew.availability.rejected', $availability, ['reason' => $data->reason]);

            return CrewAvailabilityData::fromModel($availability);
        });
    }
}
