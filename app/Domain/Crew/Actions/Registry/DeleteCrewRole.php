<?php

declare(strict_types=1);

namespace App\Domain\Crew\Actions\Registry;

use App\Domain\Crew\Models\CrewRole;
use App\Domain\Crew\Services\CrewAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class DeleteCrewRole
{
    public function __invoke(CrewRole $role): void
    {
        Gate::authorize('crew.settings.manage');
        DB::transaction(function () use ($role): void {
            $role = CrewRole::query()->lockForUpdate()->findOrFail($role->id);
            if ($role->memberRoles()->exists() || $role->requestLines()->exists() || $role->rateCards()->exists()) {
                throw ValidationException::withMessages(['role' => 'Deactivate a used role instead of deleting it.']);
            }
            CrewAudit::record('crew.role.deleted', $role);
            $role->delete();
        });
    }
}
