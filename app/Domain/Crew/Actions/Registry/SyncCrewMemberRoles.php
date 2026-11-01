<?php

declare(strict_types=1);

namespace App\Domain\Crew\Actions\Registry;

use App\Domain\Crew\Data\Registry\CrewMemberData;
use App\Domain\Crew\Data\Registry\SyncCrewMemberRolesData;
use App\Domain\Crew\Enums\CrewProficiency;
use App\Domain\Crew\Models\CrewMember;
use App\Domain\Crew\Services\CrewAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class SyncCrewMemberRoles
{
    public function __invoke(CrewMember $member, SyncCrewMemberRolesData $data): CrewMemberData
    {
        Gate::authorize('update', $member);

        return DB::transaction(function () use ($member, $data): CrewMemberData {
            $member = CrewMember::query()->lockForUpdate()->findOrFail($member->id);
            $rows = Validator::make($data->toArray(), [
                'roles' => ['present', 'array'],
                'roles.*' => ['array:crew_role_id,proficiency,years_experience,is_primary'],
                'roles.*.crew_role_id' => ['required', 'integer', 'distinct', 'exists:crew_roles,id'],
                'roles.*.proficiency' => ['required', Rule::enum(CrewProficiency::class)],
                'roles.*.years_experience' => ['nullable', 'integer', 'min:0', 'max:100'],
                'roles.*.is_primary' => ['required', 'boolean'],
            ])->validate()['roles'];
            if (count(array_filter($rows, function (array $row): bool {
                return $row['is_primary'];
            })) !== 1) {
                throw ValidationException::withMessages(['roles' => 'Select exactly one primary role.']);
            }
            $sync = [];
            foreach ($rows as $row) {
                $id = $row['crew_role_id'];
                unset($row['crew_role_id']);
                $sync[$id] = $row;
            }
            $member->roles()->sync($sync);
            CrewAudit::record('crew.member.roles_updated', $member);

            return CrewMemberData::fromModel($member);
        });
    }
}
