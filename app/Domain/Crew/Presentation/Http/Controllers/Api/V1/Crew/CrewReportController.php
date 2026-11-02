<?php

declare(strict_types=1);

namespace App\Domain\Crew\Presentation\Http\Controllers\Api\V1\Crew;

use App\Domain\Crew\Data\Planning\MatchCrewCandidatesData;
use App\Domain\Crew\Data\Planning\UtilisationFilterData;
use App\Domain\Crew\Data\Planning\WorkHistoryFilterData;
use App\Domain\Crew\Data\Registry\CrewRoleData;
use App\Domain\Crew\Models\CrewMember;
use App\Domain\Crew\Models\CrewRequest;
use App\Domain\Crew\Models\CrewRequestLine;
use App\Domain\Crew\Models\CrewRole;
use App\Domain\Crew\Presentation\Http\Requests\Crew\UtilisationFilterRequest;
use App\Domain\Crew\Presentation\Http\Requests\Crew\WorkHistoryFilterRequest;
use App\Domain\Crew\Queries\CrewCandidateQuery;
use App\Domain\Crew\Queries\CrewCostForecastQuery;
use App\Domain\Crew\Queries\CrewCoverageQuery;
use App\Domain\Crew\Queries\CrewUtilisationQuery;
use App\Domain\Crew\Queries\CrewWorkHistoryQuery;
use App\Domain\Projects\Models\Project;
use App\Http\Controllers\Api\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CrewReportController extends Controller
{
    public function coverage(Request $request, CrewCoverageQuery $query): JsonResponse
    {
        $this->authorizeApi('crew.reports.view', 'crew:read');
        $input = $request->validate(['project_id' => ['required', 'integer', 'exists:projects,id']]);
        $reports = CrewRequest::query()->where('project_id', $input['project_id'])->orderBy('id')->get()->map(function (CrewRequest $crewRequest) use ($query): array {
            return $query->forRequest($crewRequest)->toArray();
        })->all();

        return response()->json(['coverage' => $reports]);
    }

    public function costForecast(Request $request, CrewCostForecastQuery $query): JsonResponse
    {
        $this->authorizeApi('crew.reports.view', 'crew:read');
        $input = $request->validate(['project_id' => ['required', 'integer', 'exists:projects,id']]);

        return $this->respondWith($query->forProject(Project::findOrFail($input['project_id']))->toArray(), 'cost_forecast');
    }

    public function utilisation(UtilisationFilterRequest $request, CrewUtilisationQuery $query): JsonResponse
    {
        $this->authorizeApi('crew.reports.view', 'crew:read');

        return $this->respondWith($query->forPeriod(UtilisationFilterData::from($request->validated()))->toArray(), 'utilisation');
    }

    public function workHistory(WorkHistoryFilterRequest $request, CrewMember $crewMember, CrewWorkHistoryQuery $query): JsonResponse
    {
        $this->authorizeApi('crew.reports.view', 'crew:read');

        return $this->respondWith($query->forMember($crewMember->id, WorkHistoryFilterData::from($request->validated()))->toArray(), 'work_history');
    }

    public function roles(): JsonResponse
    {
        $this->authorizeApi('crew.view', 'crew:read');

        return response()->json(['roles' => CrewRole::active()->get()->map(function (CrewRole $role): array {
            return CrewRoleData::fromModel($role)->toArray();
        })->all()]);
    }

    public function candidates(Request $request, CrewRequestLine $crewRequestLine, CrewCandidateQuery $query): JsonResponse
    {
        $this->authorizeApi('crew.assignments.view', 'crew:read');
        $validated = $request->validate([
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'include_blocked' => ['sometimes', 'boolean'],
            'offset' => ['sometimes', 'integer', 'min:0'],
        ]);

        return $this->respondWith($query->reportForLine($crewRequestLine, MatchCrewCandidatesData::from($validated))->toArray(), 'matches');
    }
}
