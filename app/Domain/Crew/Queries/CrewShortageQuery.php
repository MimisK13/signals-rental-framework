<?php

declare(strict_types=1);

namespace App\Domain\Crew\Queries;

use App\Domain\Crew\Data\Planning\CrewCoverageLineData;
use App\Domain\Crew\Models\CrewRequest;
use App\Domain\Projects\Models\Project;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

final class CrewShortageQuery
{
    /** @return Collection<int,CrewCoverageLineData> */
    public function forProject(Project $project): Collection
    {
        Gate::authorize('crew.reports.view');

        return CrewRequest::query()->where('project_id', $project->id)->whereIn('status', ['open', 'partially_filled'])->get()->flatMap(function ($request): array {
            return array_values(array_filter(app(CrewCoverageQuery::class)->forRequest($request)->lines, function (CrewCoverageLineData $line): bool {
                return $line->shortage > 0;
            }));
        });
    }
}
