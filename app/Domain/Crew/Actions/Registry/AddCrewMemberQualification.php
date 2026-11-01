<?php

declare(strict_types=1);

namespace App\Domain\Crew\Actions\Registry;

use App\Domain\Crew\Data\Registry\AddCrewMemberQualificationData;
use App\Domain\Crew\Data\Registry\CrewMemberQualificationData;
use App\Domain\Crew\Models\CrewMember;
use App\Domain\Crew\Models\CrewQualification;
use App\Domain\Crew\Services\CrewAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

final class AddCrewMemberQualification
{
    public function __invoke(CrewMember $member, AddCrewMemberQualificationData $data): CrewMemberQualificationData
    {
        Gate::authorize('update', $member);

        return DB::transaction(function () use ($member, $data): CrewMemberQualificationData {
            $member = CrewMember::query()->lockForUpdate()->findOrFail($member->id);
            $qualification = CrewQualification::query()->where('is_active', true)->findOrFail($data->crew_qualification_id);
            $attributes = Validator::make($data->toArray(), [
                'crew_qualification_id' => ['required', 'integer', 'exists:crew_qualifications,id'],
                'issued_at' => ['nullable', 'date', 'before_or_equal:today'],
                'expires_at' => [$qualification->requires_expiry ? 'required' : 'nullable', 'date', 'after_or_equal:issued_at'],
                'reference_number' => ['nullable', 'string', 'max:100'], 'notes' => ['nullable', 'string', 'max:20000'],
            ])->validate();
            $record = $member->qualifications()->create($attributes);
            CrewAudit::record('crew.member.qualification_added', $record);

            return CrewMemberQualificationData::fromModel($record);
        });
    }
}
