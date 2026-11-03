<?php

declare(strict_types=1);

namespace App\Domain\Crew\Presentation\Http\Controllers\Api\V1\Crew;

use App\Domain\API\Presentation\Http\Concerns\FiltersQueries;
use App\Domain\API\Presentation\Http\Controllers\Controller;
use App\Domain\Crew\Actions\Assignments\CancelCrewAssignment;
use App\Domain\Crew\Actions\Assignments\ConfirmCrewAssignment;
use App\Domain\Crew\Actions\Assignments\InviteCrewAssignment;
use App\Domain\Crew\Actions\Assignments\ProposeCrewAssignment;
use App\Domain\Crew\Actions\Assignments\RejectCrewAssignment;
use App\Domain\Crew\Actions\Assignments\SubstituteCrewAssignment;
use App\Domain\Crew\Data\Assignments\CancelCrewAssignmentData;
use App\Domain\Crew\Data\Assignments\CrewAssignmentData;
use App\Domain\Crew\Data\Assignments\ProposeCrewAssignmentData;
use App\Domain\Crew\Data\Assignments\RejectCrewAssignmentData;
use App\Domain\Crew\Data\Assignments\SubstituteCrewAssignmentData;
use App\Domain\Crew\Models\CrewAssignment;
use App\Domain\Crew\Models\CrewRequestLine;
use App\Domain\Crew\Presentation\Http\Requests\Crew\CancelCrewAssignmentRequest;
use App\Domain\Crew\Presentation\Http\Requests\Crew\ProposeCrewAssignmentRequest;
use App\Domain\Crew\Presentation\Http\Requests\Crew\RejectCrewAssignmentRequest;
use App\Domain\Crew\Presentation\Http\Requests\Crew\SubstituteCrewAssignmentRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class CrewAssignmentController extends Controller
{
    use FiltersQueries;

    protected array $allowedFilters = ['crew_member_id', 'project_id', 'store_id', 'status'];

    protected array $allowedSorts = ['crew_member_id', 'project_id', 'store_id', 'status', 'id', 'created_at'];

    public function index(Request $request): JsonResponse
    {
        $this->authorizeApi('crew.assignments.view', 'crew:read');
        $query = CrewAssignment::query();
        $query = $this->applySort($this->applyFilters($query, $request), $request)->orderBy('id');

        $paginator = $this->paginateQuery($query, $request);
        $items = $paginator->getCollection()->map(function (CrewAssignment $record): array {
            return CrewAssignmentData::fromModel($record)->toArray();
        })->all();

        return $this->respondWithCollection($items, 'assignments', $paginator);
    }

    public function show(CrewAssignment $crewAssignment): JsonResponse
    {
        $this->authorizeApi('crew.assignments.view', 'crew:read');
        Gate::authorize('view', $crewAssignment);

        return $this->respondWith(CrewAssignmentData::fromModel($crewAssignment)->toArray(), 'assignment');
    }

    public function store(ProposeCrewAssignmentRequest $request, CrewRequestLine $crewRequestLine, ProposeCrewAssignment $action): JsonResponse
    {
        $this->authorizeApi('crew.assignments.create', 'crew:write');

        return $this->respondWith($action($crewRequestLine, ProposeCrewAssignmentData::from($request->validated()), $request->validated('override_reason'))->toArray(), 'assignment', 201);
    }

    public function invite(Request $request, CrewAssignment $crewAssignment, InviteCrewAssignment $action): JsonResponse
    {
        $this->authorizeApi('crew.assignments.create', 'crew:write');
        $result = $action($crewAssignment);

        return $this->respondWith($result->toArray(), 'assignment');
    }

    public function confirm(Request $request, CrewAssignment $crewAssignment, ConfirmCrewAssignment $action): JsonResponse
    {
        $this->authorizeApi('crew.assignments.confirm', 'crew:write');
        $result = $action($crewAssignment, $request->validate(['override_reason' => ['nullable', 'string', 'max:2000']])['override_reason'] ?? null);

        return $this->respondWith($result->toArray(), 'assignment');
    }

    public function reject(RejectCrewAssignmentRequest $request, CrewAssignment $crewAssignment, RejectCrewAssignment $action): JsonResponse
    {
        $this->authorizeApi('crew.assignments.confirm', 'crew:write');
        $result = $action($crewAssignment, RejectCrewAssignmentData::from($request->validated()));

        return $this->respondWith($result->toArray(), 'assignment');
    }

    public function cancel(CancelCrewAssignmentRequest $request, CrewAssignment $crewAssignment, CancelCrewAssignment $action): JsonResponse
    {
        $this->authorizeApi('crew.assignments.cancel', 'crew:write');
        $result = $action($crewAssignment, CancelCrewAssignmentData::from($request->validated()));

        return $this->respondWith($result->toArray(), 'assignment');
    }

    public function substitute(SubstituteCrewAssignmentRequest $request, CrewAssignment $crewAssignment, SubstituteCrewAssignment $action): JsonResponse
    {
        $this->authorizeApi('crew.assignments.substitute', 'crew:write');
        $result = $action($crewAssignment, SubstituteCrewAssignmentData::from($request->validated()), $request->validated('override_reason'));

        return $this->respondWith($result->toArray(), 'assignment');
    }
}
