<?php

declare(strict_types=1);

namespace App\Domain\Crew\Actions\Requests;

use App\Domain\Crew\Actions\Concerns\StaffingGuard;
use App\Domain\Crew\Data\Requests\CrewRequestData;
use App\Domain\Crew\Enums\CrewRequestStatus;
use App\Domain\Crew\Models\CrewRequest;
use App\Domain\Crew\Services\CrewAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class PublishCrewRequest
{
    public function __invoke(CrewRequest $request): CrewRequestData
    {
        Gate::authorize('publish', $request);

        return DB::transaction(function () use ($request): CrewRequestData {
            $request = CrewRequest::query()->lockForUpdate()->findOrFail($request->id);
            StaffingGuard::require($request->status === CrewRequestStatus::Draft, 'status', 'Only draft requests may be published.');
            StaffingGuard::require($request->lines()->exists(), 'lines', 'Add at least one role line before publishing.');
            $request->update(['status' => CrewRequestStatus::Open, 'published_at' => now()]);
            app(CrewAudit::class)->record('crew.request.published', $request);

            return CrewRequestData::fromModel($request);
        });
    }
}
