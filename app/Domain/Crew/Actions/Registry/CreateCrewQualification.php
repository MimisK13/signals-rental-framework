<?php

declare(strict_types=1);

namespace App\Domain\Crew\Actions\Registry;

use App\Domain\Crew\Data\Registry\CreateCrewQualificationData;
use App\Domain\Crew\Data\Registry\CrewQualificationData;
use App\Domain\Crew\Models\CrewQualification;
use App\Domain\Crew\Services\CrewAudit;
use App\Domain\Crew\Services\CrewTaxonomyValidation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class CreateCrewQualification
{
    public function __construct(private CrewTaxonomyValidation $validation) {}

    public function __invoke(CreateCrewQualificationData $data): CrewQualificationData
    {
        Gate::authorize('crew.settings.manage');

        return DB::transaction(function () use ($data): CrewQualificationData {

            $attributes = $this->validation->validate(CrewQualification::class, $data->toArray(), null);

            $record = CrewQualification::create($attributes);
            CrewAudit::record('crew.qualification.created', $record);

            return CrewQualificationData::fromModel($record->refresh());
        });
    }
}
