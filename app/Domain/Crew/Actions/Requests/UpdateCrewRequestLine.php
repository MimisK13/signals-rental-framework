<?php

declare(strict_types=1);

namespace App\Domain\Crew\Actions\Requests;

use App\Domain\Crew\Actions\Concerns\StaffingGuard;
use App\Domain\Crew\Data\Requests\CrewRequestLineData;
use App\Domain\Crew\Data\Requests\UpdateCrewRequestLineData;
use App\Domain\Crew\Models\CrewRequest;
use App\Domain\Crew\Models\CrewRequestLine;
use App\Domain\Crew\Services\CrewAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

final class UpdateCrewRequestLine
{
    public function __invoke(CrewRequestLine $line, UpdateCrewRequestLineData $data): CrewRequestLineData
    {
        Gate::authorize('update', $line->request);

        return DB::transaction(function () use ($line, $data): CrewRequestLineData {
            $request = CrewRequest::query()->lockForUpdate()->findOrFail($line->crew_request_id);
            $line = CrewRequestLine::query()->lockForUpdate()->findOrFail($line->id);
            StaffingGuard::editable($request);
            Validator::make($data->toArray(), ['quantity' => ['required', 'integer', 'min:1'], 'notes' => ['nullable', 'string']])->validate();
            [$start,$end] = StaffingGuard::dates($data->starts_at, $data->ends_at, $request->timezone);
            $filled = $line->assignments()->where('status', 'confirmed')->count();
            StaffingGuard::require($data->quantity >= $filled, 'quantity', 'Quantity cannot be smaller than confirmed coverage.');
            StaffingGuard::require($start->gte($request->starts_at) && $end->lte($request->ends_at), 'starts_at', 'Line dates must lie inside the request period.');
            StaffingGuard::require($filled === 0 || ($start->eq($line->effectiveStartsAt()) && $end->eq($line->effectiveEndsAt())), 'starts_at', 'Confirmed engagement dates require explicit shift realignment.');
            $line->update(['quantity' => $data->quantity, 'starts_at' => $start, 'ends_at' => $end, 'notes' => $data->notes]);
            app(CrewAudit::class)->record('crew.request.line_updated', $request, ['line_id' => $line->id]);

            return CrewRequestLineData::fromModel($line);
        });
    }
}
