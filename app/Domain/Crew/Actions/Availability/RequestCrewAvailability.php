<?php

declare(strict_types=1);

namespace App\Domain\Crew\Actions\Availability;

use App\Domain\Crew\Actions\Concerns\StaffingGuard;
use App\Domain\Crew\Data\Availability\CrewAvailabilityData;
use App\Domain\Crew\Data\Availability\RequestCrewAvailabilityData;
use App\Domain\Crew\Enums\CrewAvailabilityStatus;
use App\Domain\Crew\Enums\CrewAvailabilityType;
use App\Domain\Crew\Models\CrewAvailability;
use App\Domain\Crew\Models\CrewMember;
use App\Domain\Crew\Services\CrewAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class RequestCrewAvailability
{
    public function __invoke(CrewMember $member, RequestCrewAvailabilityData $data): CrewAvailabilityData
    {
        Gate::authorize('create', CrewAvailability::class);
        [$start,$end] = StaffingGuard::dates($data->starts_at, $data->ends_at, $data->timezone);
        Validator::make($data->toArray(), ['type' => ['required', Rule::enum(CrewAvailabilityType::class)], 'reason' => ['nullable', 'string', 'max:2000']])->validate();

        return DB::transaction(function () use ($member, $data, $start, $end): CrewAvailabilityData {
            StaffingGuard::memberLock($member->id);
            $availability = CrewAvailability::create(['crew_member_id' => $member->id, 'starts_at' => $start, 'ends_at' => $end, 'type' => $data->type, 'timezone' => $data->timezone, 'reason' => $data->reason, 'all_day' => $data->all_day, 'status' => CrewAvailabilityStatus::Requested, 'requested_by' => auth()->id()]);
            app(CrewAudit::class)->record('crew.availability.requested', $availability);

            return CrewAvailabilityData::fromModel($availability);
        });
    }
}
