<?php

declare(strict_types=1);

namespace App\Domain\Crew\Actions\Registry;

use App\Domain\Crew\Data\Registry\CrewMemberData;
use App\Domain\Crew\Models\CrewMember;
use App\Domain\Crew\Services\CrewAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class RestoreCrewMember
{
    public function __invoke(CrewMember $member): CrewMemberData
    {
        Gate::authorize('restore', $member);

        return DB::transaction(function () use ($member): CrewMemberData {
            $member = CrewMember::withTrashed()->lockForUpdate()->findOrFail($member->id);
            $member->restore();
            $member->update(['status' => 'active']);
            CrewAudit::record('crew.member.restored', $member);

            return CrewMemberData::fromModel($member);
        });
    }
}
