<?php

declare(strict_types=1);

namespace App\Domain\Crew\Actions\Requests;

use App\Domain\Crew\Actions\Concerns\StaffingGuard;
use App\Domain\Crew\Data\Requests\CrewRequestData;
use App\Domain\Crew\Data\Requests\UpdateCrewRequestData;
use App\Domain\Crew\Models\CrewRequest;
use App\Domain\Crew\Services\CrewAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

final class UpdateCrewRequest
{
    public function __invoke(CrewRequest $request, UpdateCrewRequestData $data): CrewRequestData
    {
        Gate::authorize('update', $request);

        return DB::transaction(function () use ($request, $data): CrewRequestData {
            $request = CrewRequest::query()->lockForUpdate()->findOrFail($request->id);
            StaffingGuard::editable($request);
            [$start,$end] = StaffingGuard::dates($data->starts_at, $data->ends_at, $data->timezone);
            Validator::make($data->toArray(), ['title' => ['required', 'string', 'max:160'], 'notes' => ['nullable', 'string']])->validate();
            StaffingGuard::require(! $request->lines()->where(function ($query) use ($start, $end): void {
                $query->where('starts_at', '<', $start)->orWhere('ends_at', '>', $end);
            })->exists(), 'starts_at', 'The request must contain all of its lines.');
            $request->update(['title' => $data->title, 'starts_at' => $start, 'ends_at' => $end, 'timezone' => $data->timezone, 'notes' => $data->notes]);
            app(CrewAudit::class)->record('crew.request.updated', $request);

            return CrewRequestData::fromModel($request);
        });
    }
}
