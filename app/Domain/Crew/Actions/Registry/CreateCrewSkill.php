<?php

declare(strict_types=1);

namespace App\Domain\Crew\Actions\Registry;

use App\Domain\Crew\Data\Registry\CreateCrewSkillData;
use App\Domain\Crew\Data\Registry\CrewSkillData;
use App\Domain\Crew\Models\CrewSkill;
use App\Domain\Crew\Services\CrewAudit;
use App\Domain\Crew\Services\CrewTaxonomyValidation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class CreateCrewSkill
{
    public function __construct(private CrewTaxonomyValidation $validation) {}

    public function __invoke(CreateCrewSkillData $data): CrewSkillData
    {
        Gate::authorize('crew.settings.manage');

        return DB::transaction(function () use ($data): CrewSkillData {

            $attributes = $this->validation->validate(CrewSkill::class, $data->toArray(), null);

            $record = CrewSkill::create($attributes);
            CrewAudit::record('crew.skill.created', $record);

            return CrewSkillData::fromModel($record->refresh());
        });
    }
}
