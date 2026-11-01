<?php

declare(strict_types=1);

namespace App\Domain\Crew\Actions\Registry;

use App\Domain\Crew\Models\CrewMemberQualification;
use App\Domain\Crew\Services\CrewAudit;
use App\Models\Scopes\StoreScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class DeleteCrewMemberQualification
{
    public function __invoke(CrewMemberQualification $qualification): void
    {
        Gate::authorize('update', $qualification->crewMember);
        DB::transaction(function () use ($qualification): void {
            $qualification = CrewMemberQualification::query()->lockForUpdate()->findOrFail($qualification->id);
            if ($qualification->verified_at !== null || $qualification->crewMember->assignments()->withoutGlobalScope(StoreScope::class)->exists()) {
                throw ValidationException::withMessages(['qualification' => 'Verified or historically used qualifications must be retained.']);
            }
            CrewAudit::record('crew.member.qualification_deleted', $qualification);
            $qualification->delete();
        });
    }
}
