<?php

declare(strict_types=1);

namespace App\Domain\Crew\Actions\Registry;

use App\Domain\Crew\Data\Registry\CrewMemberData;
use App\Domain\Crew\Data\Registry\LinkCrewMemberUserData;
use App\Domain\Crew\Models\CrewMember;
use App\Domain\Crew\Services\CrewAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class LinkCrewMemberUser
{
    public function __invoke(CrewMember $member, LinkCrewMemberUserData $data): CrewMemberData
    {
        Gate::authorize('update', $member);

        return DB::transaction(function () use ($member, $data): CrewMemberData {
            $member = CrewMember::query()->lockForUpdate()->findOrFail($member->id);
            $attributes = Validator::make($data->toArray(), ['user_id' => ['nullable', 'integer', 'exists:users,id', Rule::unique('crew_members', 'user_id')->ignore($member->id)]])->validate();
            $member->update($attributes);
            CrewAudit::record('crew.member.user_linked', $member);

            return CrewMemberData::fromModel($member);
        });
    }
}
