<?php

declare(strict_types=1);

namespace App\Domain\Crew\Actions\Registry;

use App\Domain\Crew\Data\Registry\CrewMemberQualificationData;
use App\Domain\Crew\Models\CrewMemberQualification;
use App\Domain\Crew\Services\CrewAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class VerifyCrewMemberQualification
{
    public function __invoke(CrewMemberQualification $qualification): CrewMemberQualificationData
    {
        Gate::authorize('crew.settings.manage');

        return DB::transaction(function () use ($qualification): CrewMemberQualificationData {
            $qualification = CrewMemberQualification::query()->lockForUpdate()->findOrFail($qualification->id);
            if ($qualification->expires_at?->lt(today())) {
                throw ValidationException::withMessages(['expires_at' => 'An expired certificate cannot be verified.']);
            }
            $qualification->update(['verified_at' => now(), 'verified_by' => auth()->id()]);
            CrewAudit::record('crew.member.qualification_verified', $qualification);

            return CrewMemberQualificationData::fromModel($qualification);
        });
    }
}
