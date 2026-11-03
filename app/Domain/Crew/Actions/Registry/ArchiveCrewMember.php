<?php

declare(strict_types=1);

namespace App\Domain\Crew\Actions\Registry;

use App\Domain\Crew\Data\Registry\CrewMemberData;
use App\Domain\Crew\Models\CrewMember;
use App\Domain\Crew\Services\CrewAudit;
use App\Domain\Stores\Scopes\StoreScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class ArchiveCrewMember
{
    public function __invoke(CrewMember $member): CrewMemberData
    {
        Gate::authorize('delete', $member);

        return DB::transaction(function () use ($member): CrewMemberData {
            $member = CrewMember::withTrashed()->lockForUpdate()->findOrFail($member->id);
            if ($member->assignments()->withoutGlobalScope(StoreScope::class)->where('status', 'confirmed')->where(function ($query): void {
                $query->whereHas('shifts', function ($shifts): void {
                    $shifts->withoutGlobalScope(StoreScope::class)->where('ends_at', '>', now())->whereNotIn('status', ['cancelled', 'completed']);
                })->orWhereHas('line', function ($lines): void {
                    $lines->whereRaw('COALESCE(crew_request_lines.ends_at, (SELECT ends_at FROM crew_requests WHERE crew_requests.id = crew_request_lines.crew_request_id)) > ?', [now()]);
                });
            })->exists()) {
                throw ValidationException::withMessages(['crew_member' => 'Cancel future confirmed assignments before archiving.']);
            }
            $member->update(['status' => 'archived']);
            $member->delete();
            CrewAudit::record('crew.member.archived', $member);

            return CrewMemberData::fromModel($member);
        });
    }
}
