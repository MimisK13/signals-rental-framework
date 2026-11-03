<?php

declare(strict_types=1);

namespace App\Domain\Crew\Actions\Requests;

use App\Domain\Crew\Actions\Concerns\StaffingGuard;
use App\Domain\Crew\Data\Requests\CreateCrewRequestData;
use App\Domain\Crew\Data\Requests\CrewRequestData;
use App\Domain\Crew\Enums\CrewRequestStatus;
use App\Domain\Crew\Models\CrewRequest;
use App\Domain\Crew\Services\CrewAudit;
use App\Domain\Projects\Opportunities\Models\Opportunity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

final class CreateCrewRequest
{
    public function __invoke(CreateCrewRequestData $data): CrewRequestData
    {
        Gate::authorize('create', CrewRequest::class);
        $attributes = Validator::make($data->toArray(), [
            'title' => ['required', 'string', 'max:160'], 'project_id' => ['required', 'integer', 'exists:projects,id'],
            'store_id' => ['required', 'integer', 'exists:stores,id'], 'opportunity_id' => ['nullable', 'integer', 'exists:opportunities,id'], 'notes' => ['nullable', 'string'],
        ])->validate();
        Gate::allowIf(auth()->user()->canAccessStore($data->store_id));
        [$start,$end] = StaffingGuard::dates($data->starts_at, $data->ends_at, $data->timezone);
        if ($data->opportunity_id !== null) {
            $opportunity = Opportunity::query()->findOrFail($data->opportunity_id);
            StaffingGuard::require($opportunity->project_id === $data->project_id && $opportunity->store_id === $data->store_id, 'opportunity_id', 'The opportunity must belong to the selected project and store.');
        }

        return DB::transaction(function () use ($attributes, $data, $start, $end): CrewRequestData {
            $request = CrewRequest::create([...$attributes, 'starts_at' => $start, 'ends_at' => $end, 'timezone' => $data->timezone, 'status' => CrewRequestStatus::Draft, 'requested_by' => auth()->id()]);
            app(CrewAudit::class)->record('crew.request.created', $request);

            return CrewRequestData::fromModel($request);
        });
    }
}
