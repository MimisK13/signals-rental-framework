<?php

declare(strict_types=1);

namespace App\Domain\Crew\Actions\Registry;

use App\Domain\Crew\Data\Registry\CrewMemberData;
use App\Domain\Crew\Data\Registry\UpdateCrewMemberData;
use App\Domain\Crew\Models\CrewMember;
use App\Domain\Crew\Services\CrewAudit;
use App\Domain\Crew\Services\CrewMemberValidation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class UpdateCrewMember
{
    public function __construct(private CrewMemberValidation $validation) {}

    public function __invoke(CrewMember $member, UpdateCrewMemberData $data): CrewMemberData
    {
        Gate::authorize('update', $member);

        return DB::transaction(function () use ($member, $data): CrewMemberData {
            $member = CrewMember::query()->lockForUpdate()->findOrFail($member->id);
            $attributes = $this->validation->validate($data->toArray(), $member);
            $member->update($attributes);
            CrewAudit::record('crew.member.updated', $member);

            return CrewMemberData::fromModel($member->refresh());
        });
    }
}
