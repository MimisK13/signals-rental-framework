<?php

declare(strict_types=1);

namespace App\Domain\Crew\Actions\Registry;

use App\Domain\Crew\Data\Registry\CrewQualificationData;
use App\Domain\Crew\Data\Registry\UpdateCrewQualificationData;
use App\Domain\Crew\Models\CrewQualification;
use App\Domain\Crew\Services\CrewAudit;
use App\Domain\Crew\Services\CrewTaxonomyValidation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class UpdateCrewQualification
{
    public function __construct(private CrewTaxonomyValidation $validation) {}

    public function __invoke(CrewQualification $record, UpdateCrewQualificationData $data): CrewQualificationData
    {
        Gate::authorize('crew.settings.manage');

        return DB::transaction(function () use ($record, $data): CrewQualificationData {
            $record = CrewQualification::query()->lockForUpdate()->findOrFail($record->id);
            $attributes = $this->validation->validate(CrewQualification::class, $data->toArray(), $record);
            if ($record->validity_months !== null && $attributes['validity_months'] !== null && $attributes['validity_months'] < $record->validity_months && $record->memberQualifications()->where('expires_at', '>=', today())->exists()) {
                throw ValidationException::withMessages(['validity_months' => 'Cannot shorten validity while current certificates exist.']);
            }
            $record->update($attributes);
            CrewAudit::record('crew.qualification.updated', $record);

            return CrewQualificationData::fromModel($record->refresh());
        });
    }
}
