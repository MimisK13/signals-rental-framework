<?php

declare(strict_types=1);

namespace App\Domain\Crew\Actions\Registry;

use App\Domain\Crew\Data\Registry\CrewRoleData;
use App\Domain\Crew\Data\Registry\UpdateCrewRoleData;
use App\Domain\Crew\Models\CrewRole;
use App\Domain\Crew\Services\CrewAudit;
use App\Domain\Crew\Services\CrewTaxonomyValidation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class UpdateCrewRole
{
    public function __construct(private CrewTaxonomyValidation $validation) {}

    public function __invoke(CrewRole $record, UpdateCrewRoleData $data): CrewRoleData
    {
        Gate::authorize('crew.settings.manage');

        return DB::transaction(function () use ($record, $data): CrewRoleData {
            $record = CrewRole::query()->lockForUpdate()->findOrFail($record->id);
            $attributes = $this->validation->validate(CrewRole::class, $data->toArray(), $record);
            if (! $attributes['is_active'] && $record->requestLines()->whereHas('request', function ($query): void {
                $query->whereIn('status', ['open', 'partially_filled', 'filled']);
            })->exists()) {
                throw ValidationException::withMessages(['is_active' => 'This role is used by an open request.']);
            }
            $record->update($attributes);
            CrewAudit::record('crew.role.updated', $record);

            return CrewRoleData::fromModel($record->refresh());
        });
    }
}
