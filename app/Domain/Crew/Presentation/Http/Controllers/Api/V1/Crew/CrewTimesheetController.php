<?php

declare(strict_types=1);

namespace App\Domain\Crew\Presentation\Http\Controllers\Api\V1\Crew;

use App\Domain\API\Presentation\Http\Concerns\FiltersQueries;
use App\Domain\API\Presentation\Http\Controllers\Controller;
use App\Domain\Crew\Actions\Timesheets\AddCrewTimeEntry;
use App\Domain\Crew\Actions\Timesheets\ApproveCrewTimesheet;
use App\Domain\Crew\Actions\Timesheets\CreateCrewTimesheet;
use App\Domain\Crew\Actions\Timesheets\ExportCrewTimesheetToFinance;
use App\Domain\Crew\Actions\Timesheets\LockCrewTimesheet;
use App\Domain\Crew\Actions\Timesheets\RejectCrewTimesheet;
use App\Domain\Crew\Actions\Timesheets\SubmitCrewTimesheet;
use App\Domain\Crew\Data\Timesheets\AddCrewTimeEntryData;
use App\Domain\Crew\Data\Timesheets\ApproveCrewTimesheetData;
use App\Domain\Crew\Data\Timesheets\CreateCrewTimesheetData;
use App\Domain\Crew\Data\Timesheets\CrewTimesheetData;
use App\Domain\Crew\Data\Timesheets\ExportToFinanceData;
use App\Domain\Crew\Data\Timesheets\RejectCrewTimesheetData;
use App\Domain\Crew\Models\CrewAssignment;
use App\Domain\Crew\Models\CrewTimesheet;
use App\Domain\Crew\Presentation\Http\Requests\Crew\AddCrewTimeEntryRequest;
use App\Domain\Crew\Presentation\Http\Requests\Crew\ApproveCrewTimesheetRequest;
use App\Domain\Crew\Presentation\Http\Requests\Crew\CreateCrewTimesheetRequest;
use App\Domain\Crew\Presentation\Http\Requests\Crew\ExportToFinanceRequest;
use App\Domain\Crew\Presentation\Http\Requests\Crew\RejectCrewTimesheetRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class CrewTimesheetController extends Controller
{
    use FiltersQueries;

    protected array $allowedFilters = ['crew_member_id', 'project_id', 'store_id', 'status', 'period_start', 'period_end'];

    protected array $allowedSorts = ['crew_member_id', 'project_id', 'store_id', 'status', 'period_start', 'period_end', 'id', 'created_at'];

    public function index(Request $request): JsonResponse
    {
        $this->authorizeApi('crew.timesheets.view', 'crew:read');
        $query = CrewTimesheet::query();
        $query = $this->applySort($this->applyFilters($query, $request), $request)->orderBy('id');
        $query->with('entries');
        $paginator = $this->paginateQuery($query, $request);
        $items = $paginator->getCollection()->map(function (CrewTimesheet $record): array {
            return CrewTimesheetData::fromModel($record)->toArray();
        })->all();

        return $this->respondWithCollection($items, 'timesheets', $paginator);
    }

    public function show(CrewTimesheet $crewTimesheet): JsonResponse
    {
        $this->authorizeApi('crew.timesheets.view', 'crew:read');
        Gate::authorize('view', $crewTimesheet);

        return $this->respondWith(CrewTimesheetData::fromModel($crewTimesheet)->toArray(), 'timesheet');
    }

    public function submit(Request $request, CrewTimesheet $crewTimesheet, SubmitCrewTimesheet $action): JsonResponse
    {
        $this->authorizeApi('crew.timesheets.create', 'crew:write');
        $result = $action($crewTimesheet);

        return $this->respondWith($result->toArray(), 'timesheet');
    }

    public function approve(ApproveCrewTimesheetRequest $request, CrewTimesheet $crewTimesheet, ApproveCrewTimesheet $action): JsonResponse
    {
        $this->authorizeApi('crew.timesheets.approve', 'crew:write');
        $result = $action($crewTimesheet, ApproveCrewTimesheetData::from($request->validated()));

        return $this->respondWith($result->toArray(), 'timesheet');
    }

    public function reject(RejectCrewTimesheetRequest $request, CrewTimesheet $crewTimesheet, RejectCrewTimesheet $action): JsonResponse
    {
        $this->authorizeApi('crew.timesheets.approve', 'crew:write');
        $result = $action($crewTimesheet, RejectCrewTimesheetData::from($request->validated()));

        return $this->respondWith($result->toArray(), 'timesheet');
    }

    public function lock(Request $request, CrewTimesheet $crewTimesheet, LockCrewTimesheet $action): JsonResponse
    {
        $this->authorizeApi('crew.timesheets.lock', 'crew:write');
        $result = $action($crewTimesheet);

        return $this->respondWith($result->toArray(), 'timesheet');
    }

    public function store(CreateCrewTimesheetRequest $request, CrewAssignment $crewAssignment, CreateCrewTimesheet $action): JsonResponse
    {
        $this->authorizeApi('crew.timesheets.create', 'crew:write');

        return $this->respondWith($action($crewAssignment, CreateCrewTimesheetData::from($request->validated()))->toArray(), 'timesheet', 201);
    }

    public function addEntry(AddCrewTimeEntryRequest $request, CrewTimesheet $crewTimesheet, AddCrewTimeEntry $action): JsonResponse
    {
        $this->authorizeApi('crew.timesheets.create', 'crew:write');

        return $this->respondWith($action($crewTimesheet, AddCrewTimeEntryData::from($request->validated()))->toArray(), 'entry', 201);
    }

    public function export(ExportToFinanceRequest $request, CrewTimesheet $crewTimesheet, ExportCrewTimesheetToFinance $action): JsonResponse
    {
        $this->authorizeApi('crew.timesheets.export', 'crew:write');

        return $this->respondWith($action($crewTimesheet, ExportToFinanceData::from($request->validated()))->toArray(), 'timesheet', 200);
    }
}
