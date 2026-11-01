<?php

declare(strict_types=1);

namespace App\Domain\Crew\Actions\Registry;

use App\Domain\Crew\Data\Registry\CreateCrewRoleData;
use App\Domain\Crew\Data\Registry\CrewRoleData;
use App\Domain\Crew\Models\CrewRole;
use App\Domain\Crew\Services\CrewAudit;
use App\Domain\Crew\Services\CrewTaxonomyValidation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class CreateCrewRole
{
    public function __construct(private CrewTaxonomyValidation $validation) {}

    public function __invoke(CreateCrewRoleData $data): CrewRoleData
    {
        Gate::authorize('crew.settings.manage');

        return DB::transaction(function () use ($data): CrewRoleData {

            $attributes = $this->validation->validate(CrewRole::class, $data->toArray(), null);

            $record = CrewRole::create($attributes);
            CrewAudit::record('crew.role.created', $record);

            return CrewRoleData::fromModel($record->refresh());
        });
    }
}
