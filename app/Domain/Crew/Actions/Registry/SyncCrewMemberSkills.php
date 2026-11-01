<?php

declare(strict_types=1);

namespace App\Domain\Crew\Actions\Registry;

use App\Domain\Crew\Data\Registry\CrewMemberData;
use App\Domain\Crew\Data\Registry\SyncCrewMemberSkillsData;
use App\Domain\Crew\Enums\CrewProficiency;
use App\Domain\Crew\Models\CrewMember;
use App\Domain\Crew\Services\CrewAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class SyncCrewMemberSkills
{
    public function __invoke(CrewMember $member, SyncCrewMemberSkillsData $data): CrewMemberData
    {
        Gate::authorize('update', $member);

        return DB::transaction(function () use ($member, $data): CrewMemberData {
            $member = CrewMember::query()->lockForUpdate()->findOrFail($member->id);
            $rows = Validator::make($data->toArray(), [
                'skills' => ['present', 'array'],
                'skills.*' => ['array:crew_skill_id,proficiency,years_experience'],
                'skills.*.crew_skill_id' => ['required', 'integer', 'distinct', 'exists:crew_skills,id'],
                'skills.*.proficiency' => ['required', Rule::enum(CrewProficiency::class)],
                'skills.*.years_experience' => ['nullable', 'integer', 'min:0', 'max:100'],

            ])->validate()['skills'];

            $sync = [];
            foreach ($rows as $row) {
                $id = $row['crew_skill_id'];
                unset($row['crew_skill_id']);
                $sync[$id] = $row;
            }
            $member->skills()->sync($sync);
            CrewAudit::record('crew.member.skills_updated', $member);

            return CrewMemberData::fromModel($member);
        });
    }
}
