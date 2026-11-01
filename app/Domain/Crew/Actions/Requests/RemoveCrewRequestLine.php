<?php

declare(strict_types=1);

namespace App\Domain\Crew\Actions\Requests;

use App\Domain\Crew\Actions\Concerns\StaffingGuard;
use App\Domain\Crew\Models\CrewRequest;
use App\Domain\Crew\Models\CrewRequestLine;
use App\Domain\Crew\Services\CrewAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class RemoveCrewRequestLine
{
    public function __invoke(CrewRequestLine $line): void
    {
        Gate::authorize('update', $line->request);
        DB::transaction(function () use ($line): void {
            $request = CrewRequest::query()->lockForUpdate()->findOrFail($line->crew_request_id);
            StaffingGuard::editable($request);
            StaffingGuard::require(! $line->assignments()->exists(), 'line', 'A line with assignment history cannot be removed.');
            $id = $line->id;
            $line->delete();
            app(CrewAudit::class)->record('crew.request.line_removed', $request, ['line_id' => $id]);
        });
    }
}
