<?php

declare(strict_types=1);

namespace App\Domain\Crew\Actions\Requests;

use App\Domain\Crew\Actions\Concerns\StaffingGuard;
use App\Domain\Crew\Data\Requests\CrewRequestLineData;
use App\Domain\Crew\Data\Requests\SyncLineRequirementsData;
use App\Domain\Crew\Enums\CrewProficiency;
use App\Domain\Crew\Models\CrewRequest;
use App\Domain\Crew\Models\CrewRequestLine;
use App\Domain\Crew\Services\CrewAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class SyncCrewRequestLineRequirements
{
    public function __invoke(CrewRequestLine $line, SyncLineRequirementsData $data): CrewRequestLineData
    {
        Gate::authorize('update', $line->request);
        $requirements = Validator::make($data->toArray(), [
            'requirements' => ['array'], 'requirements.*' => ['array:requirement_type,crew_skill_id,crew_qualification_id,is_mandatory,min_proficiency'],
            'requirements.*.requirement_type' => ['required', Rule::in(['skill', 'qualification'])],
            'requirements.*.crew_skill_id' => ['nullable', 'integer', 'exists:crew_skills,id'],
            'requirements.*.crew_qualification_id' => ['nullable', 'integer', 'exists:crew_qualifications,id'],
            'requirements.*.is_mandatory' => ['required', 'boolean'],
            'requirements.*.min_proficiency' => ['nullable', Rule::enum(CrewProficiency::class)],
        ])->validate()['requirements'];
        foreach ($requirements as $index => $requirement) {
            $skill = $requirement['crew_skill_id'] ?? null;
            $qualification = $requirement['crew_qualification_id'] ?? null;
            StaffingGuard::require($requirement['requirement_type'] === 'skill' ? ($skill !== null && $qualification === null) : ($qualification !== null && $skill === null), 'requirements.'.$index, 'Each requirement must reference exactly one target of its declared type.');
        }

        return DB::transaction(function () use ($line, $requirements): CrewRequestLineData {
            $request = CrewRequest::query()->lockForUpdate()->findOrFail($line->crew_request_id);
            $line = CrewRequestLine::query()->lockForUpdate()->findOrFail($line->id);
            StaffingGuard::editable($request);
            StaffingGuard::require(! $line->assignments()->where('status', 'confirmed')->exists(), 'requirements', 'Requirements cannot change after staffing is confirmed.');
            $line->requirements()->delete();
            $line->requirements()->createMany($requirements);
            app(CrewAudit::class)->record('crew.request.requirements_updated', $request, ['line_id' => $line->id]);

            return CrewRequestLineData::fromModel($line);
        });
    }
}
