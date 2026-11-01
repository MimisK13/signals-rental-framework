<?php

declare(strict_types=1);

namespace App\Domain\Crew\Queries;

use App\Domain\Crew\Data\Registry\CrewMemberQualificationData;
use App\Domain\Crew\Models\CrewMemberQualification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

final class CrewExpiringQualificationQuery
{
    /** @return Collection<int,CrewMemberQualificationData> */
    public function dueWithin(int $days): Collection
    {
        Gate::authorize('crew.view');
        Validator::make(['days' => $days], ['days' => ['integer', 'min:0', 'max:365']])->validate();

        return CrewMemberQualification::query()->whereNotNull('expires_at')->whereDate('expires_at', '<=', today()->addDays($days))->whereHas('crewMember', function ($query): void {
            $query->where('status', 'active');
        })->orderBy('expires_at')->get()->map(function (CrewMemberQualification $record): CrewMemberQualificationData {
            return CrewMemberQualificationData::fromModel($record);
        });
    }
}
