<?php

declare(strict_types=1);

namespace App\Domain\Crew\Actions\Registry;

use App\Domain\Crew\Data\Registry\CrewSkillData;
use App\Domain\Crew\Data\Registry\UpdateCrewSkillData;
use App\Domain\Crew\Models\CrewSkill;
use App\Domain\Crew\Services\CrewAudit;
use App\Domain\Crew\Services\CrewTaxonomyValidation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class UpdateCrewSkill
{
    public function __construct(private CrewTaxonomyValidation $validation) {}

    public function __invoke(CrewSkill $record, UpdateCrewSkillData $data): CrewSkillData
    {
        Gate::authorize('crew.settings.manage');

        return DB::transaction(function () use ($record, $data): CrewSkillData {
            $record = CrewSkill::query()->lockForUpdate()->findOrFail($record->id);
            $attributes = $this->validation->validate(CrewSkill::class, $data->toArray(), $record);

            $record->update($attributes);
            CrewAudit::record('crew.skill.updated', $record);

            return CrewSkillData::fromModel($record->refresh());
        });
    }
}
