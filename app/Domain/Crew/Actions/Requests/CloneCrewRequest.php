<?php

declare(strict_types=1);

namespace App\Domain\Crew\Actions\Requests;

use App\Domain\Crew\Data\Requests\CloneCrewRequestData;
use App\Domain\Crew\Data\Requests\CrewRequestData;
use App\Domain\Crew\Enums\CrewRequestStatus;
use App\Domain\Crew\Models\CrewRequest;
use App\Domain\Crew\Services\CrewAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

final class CloneCrewRequest
{
    public function __invoke(CrewRequest $request, CloneCrewRequestData $data): CrewRequestData
    {
        Gate::authorize('view', $request);
        Gate::authorize('create', CrewRequest::class);
        Validator::make($data->toArray(), ['title' => ['required', 'string', 'max:160']])->validate();

        return DB::transaction(function () use ($request, $data): CrewRequestData {
            $request->load(['lines.requirements']);
            $clone = $request->replicate(['reference', 'published_at', 'approved_at', 'approved_by', 'cancelled_at', 'cancellation_reason']);
            $clone->title = $data->title;
            $clone->status = CrewRequestStatus::Draft;
            $clone->requested_by = auth()->id();
            $clone->save();
            foreach ($request->lines as $line) {
                $new = $line->replicate();
                $new->crew_request_id = $clone->id;
                $new->filled_quantity = 0;
                $new->save();
                foreach ($line->requirements as $requirement) {
                    $copy = $requirement->replicate();
                    $copy->crew_request_line_id = $new->id;
                    $copy->save();
                }
            }
            app(CrewAudit::class)->record('crew.request.created', $clone, ['cloned_from' => $request->id]);

            return CrewRequestData::fromModel($clone);
        });
    }
}
