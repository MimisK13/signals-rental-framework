<?php

declare(strict_types=1);

namespace App\Domain\Crew\Actions\Requests;

use App\Domain\Crew\Actions\Concerns\StaffingGuard;
use App\Domain\Crew\Data\Requests\AddCrewRequestLineData;
use App\Domain\Crew\Data\Requests\CrewRequestLineData;
use App\Domain\Crew\Models\CrewRequest;
use App\Domain\Crew\Services\CrewAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class AddCrewRequestLine
{
    public function __invoke(CrewRequest $request, AddCrewRequestLineData $data): CrewRequestLineData
    {
        Gate::authorize('update', $request);

        return DB::transaction(function () use ($request, $data): CrewRequestLineData {
            $request = CrewRequest::query()->lockForUpdate()->findOrFail($request->id);
            StaffingGuard::editable($request);
            Validator::make($data->toArray(), ['crew_role_id' => ['required', 'integer', Rule::exists('crew_roles', 'id')->where('is_active', true)], 'quantity' => ['required', 'integer', 'min:1'], 'notes' => ['nullable', 'string']])->validate();
            [$start,$end] = StaffingGuard::dates($data->starts_at, $data->ends_at, $request->timezone);
            StaffingGuard::require($start->gte($request->starts_at) && $end->lte($request->ends_at), 'starts_at', 'Line dates must lie inside the request period.');
            $line = $request->lines()->create(['crew_role_id' => $data->crew_role_id, 'quantity' => $data->quantity, 'filled_quantity' => 0, 'starts_at' => $start, 'ends_at' => $end, 'notes' => $data->notes]);
            app(CrewAudit::class)->record('crew.request.line_added', $request, ['line_id' => $line->id]);

            return CrewRequestLineData::fromModel($line);
        });
    }
}
