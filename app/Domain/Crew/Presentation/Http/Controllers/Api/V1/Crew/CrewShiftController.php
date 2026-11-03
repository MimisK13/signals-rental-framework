<?php

declare(strict_types=1);

namespace App\Domain\Crew\Presentation\Http\Controllers\Api\V1\Crew;

use App\Domain\API\Presentation\Http\Concerns\FiltersQueries;
use App\Domain\API\Presentation\Http\Controllers\Controller;
use App\Domain\Crew\Actions\Shifts\CancelCrewShift;
use App\Domain\Crew\Actions\Shifts\ConfirmCrewShift;
use App\Domain\Crew\Actions\Shifts\CreateCrewShift;
use App\Domain\Crew\Actions\Shifts\UpdateCrewShift;
use App\Domain\Crew\Data\Shifts\CancelCrewShiftData;
use App\Domain\Crew\Data\Shifts\CreateCrewShiftData;
use App\Domain\Crew\Data\Shifts\CrewShiftData;
use App\Domain\Crew\Data\Shifts\UpdateCrewShiftData;
use App\Domain\Crew\Models\CrewAssignment;
use App\Domain\Crew\Models\CrewShift;
use App\Domain\Crew\Presentation\Http\Requests\Crew\CancelCrewShiftRequest;
use App\Domain\Crew\Presentation\Http\Requests\Crew\CreateCrewShiftRequest;
use App\Domain\Crew\Presentation\Http\Requests\Crew\UpdateCrewShiftRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class CrewShiftController extends Controller
{
    use FiltersQueries;

    protected array $allowedFilters = ['crew_member_id', 'project_id', 'store_id', 'status', 'starts_at', 'ends_at'];

    protected array $allowedSorts = ['crew_member_id', 'project_id', 'store_id', 'status', 'starts_at', 'ends_at', 'id', 'created_at'];

    public function index(Request $request): JsonResponse
    {
        $this->authorizeApi('crew.shifts.view', 'crew:read');
        $query = CrewShift::query();
        $query = $this->applySort($this->applyFilters($query, $request), $request)->orderBy('id');

        $paginator = $this->paginateQuery($query, $request);
        $items = $paginator->getCollection()->map(function (CrewShift $record): array {
            return CrewShiftData::fromModel($record)->toArray();
        })->all();

        return $this->respondWithCollection($items, 'shifts', $paginator);
    }

    public function show(CrewShift $crewShift): JsonResponse
    {
        $this->authorizeApi('crew.shifts.view', 'crew:read');
        Gate::authorize('view', $crewShift);

        return $this->respondWith(CrewShiftData::fromModel($crewShift)->toArray(), 'shift');
    }

    public function store(CreateCrewShiftRequest $request, CrewAssignment $crewAssignment, CreateCrewShift $action): JsonResponse
    {
        $this->authorizeApi('crew.shifts.manage', 'crew:write');

        return $this->respondWith($action($crewAssignment, CreateCrewShiftData::from($request->validated()), $request->validated('override_reason'))->toArray(), 'shift', 201);
    }

    public function update(UpdateCrewShiftRequest $request, CrewShift $crewShift, UpdateCrewShift $action): JsonResponse
    {
        $this->authorizeApi('crew.shifts.manage', 'crew:write');

        return $this->respondWith($action($crewShift, UpdateCrewShiftData::from($request->validated()), $request->validated('override_reason'))->toArray(), 'shift', 200);
    }

    public function confirm(Request $request, CrewShift $crewShift, ConfirmCrewShift $action): JsonResponse
    {
        $this->authorizeApi('crew.shifts.manage', 'crew:write');
        $result = $action($crewShift);

        return $this->respondWith($result->toArray(), 'shift');
    }

    public function destroy(CancelCrewShiftRequest $request, CrewShift $crewShift, CancelCrewShift $action): JsonResponse
    {
        $this->authorizeApi('crew.shifts.manage', 'crew:write');
        $result = $action($crewShift, CancelCrewShiftData::from($request->validated()));

        return $this->respondWith($result->toArray(), 'shift');
    }
}
