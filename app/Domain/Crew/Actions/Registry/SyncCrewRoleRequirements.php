<?php

declare(strict_types=1);

namespace App\Domain\Crew\Actions\Registry;

use App\Domain\Crew\Data\Registry\CrewRoleData;
use App\Domain\Crew\Data\Registry\SyncRoleRequirementsData;
use App\Domain\Crew\Enums\CrewProficiency;
use App\Domain\Crew\Models\CrewRole;
use App\Domain\Crew\Services\CrewAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class SyncCrewRoleRequirements
{
    public function __invoke(CrewRole $role, SyncRoleRequirementsData $data): CrewRoleData
    {
        Gate::authorize('crew.settings.manage');

        return DB::transaction(function () use ($role, $data): CrewRoleData {
            $role = CrewRole::query()->lockForUpdate()->findOrFail($role->id);
            $rows = Validator::make($data->toArray(), [
                'requirements' => ['present', 'array'],
                'requirements.*' => ['array:requirement_type,crew_skill_id,crew_qualification_id,is_mandatory,min_proficiency,min_years_experience'],
                'requirements.*.requirement_type' => ['required', 'in:skill,qualification'],
                'requirements.*.crew_skill_id' => ['nullable', 'integer', 'exists:crew_skills,id'],
                'requirements.*.crew_qualification_id' => ['nullable', 'integer', 'exists:crew_qualifications,id'],
                'requirements.*.is_mandatory' => ['required', 'boolean'],
                'requirements.*.min_proficiency' => ['nullable', Rule::enum(CrewProficiency::class)],
                'requirements.*.min_years_experience' => ['nullable', 'integer', 'min:0', 'max:100'],
            ])->validate()['requirements'];
            $seen = [];
            foreach ($rows as $row) {
                $skill = $row['crew_skill_id'] ?? null;
                $qualification = $row['crew_qualification_id'] ?? null;
                if (($row['requirement_type'] === 'skill' && ($skill === null || $qualification !== null)) || ($row['requirement_type'] === 'qualification' && ($qualification === null || $skill !== null))) {
                    throw ValidationException::withMessages(['requirements' => 'Each requirement must specify exactly its matching target.']);
                }
                $key = $row['requirement_type'].':'.($skill ?? $qualification);
                if (isset($seen[$key])) {
                    throw ValidationException::withMessages(['requirements' => 'A target may only appear once.']);
                }
                $seen[$key] = true;
            }
            $role->requirements()->delete();
            $role->requirements()->createMany($rows);
            CrewAudit::record('crew.role.requirements_updated', $role);

            return CrewRoleData::fromModel($role);
        });
    }
}
