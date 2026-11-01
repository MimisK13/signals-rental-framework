<?php

declare(strict_types=1);

namespace App\Domain\Crew\Presentation\Http\Controllers\Api\V1\Crew;

use App\Domain\Crew\Actions\Requests\AddCrewRequestLine;
use App\Domain\Crew\Actions\Requests\CancelCrewRequest;
use App\Domain\Crew\Actions\Requests\CreateCrewRequest;
use App\Domain\Crew\Actions\Requests\PublishCrewRequest;
use App\Domain\Crew\Actions\Requests\UpdateCrewRequest;
use App\Domain\Crew\Data\Requests\AddCrewRequestLineData;
use App\Domain\Crew\Data\Requests\CancelCrewRequestData;
use App\Domain\Crew\Data\Requests\CreateCrewRequestData;
use App\Domain\Crew\Data\Requests\CrewRequestData;
use App\Domain\Crew\Data\Requests\UpdateCrewRequestData;
use App\Domain\Crew\Models\CrewRequest;
use App\Domain\Crew\Presentation\Http\Requests\Crew\AddCrewRequestLineRequest;
use App\Domain\Crew\Presentation\Http\Requests\Crew\CancelCrewRequestRequest;
use App\Domain\Crew\Presentation\Http\Requests\Crew\CreateCrewRequestRequest;
use App\Domain\Crew\Presentation\Http\Requests\Crew\UpdateCrewRequestRequest;
use App\Http\Controllers\Api\Controller;
use App\Http\Traits\FiltersQueries;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class CrewRequestController extends Controller
{
    use FiltersQueries;

    protected ?string $customFieldModule = 'CrewRequest';

    protected array $allowedFilters = ['title', 'status', 'project_id', 'store_id'];

    protected array $allowedSorts = ['title', 'status', 'project_id', 'store_id', 'id', 'created_at'];

    public function index(Request $request): JsonResponse
    {
        $this->authorizeApi('crew.requests.view', 'crew:read');
        $query = CrewRequest::query();
        $query = $this->applySort($this->applyFilters($query, $request), $request)->orderBy('id');

        $paginator = $this->paginateQuery($query, $request);
        $items = $paginator->getCollection()->map(function (CrewRequest $record): array {
            return CrewRequestData::fromModel($record)->toArray();
        })->all();

        return $this->respondWithCollection($items, 'requests', $paginator);
    }

    public function show(CrewRequest $crewRequest): JsonResponse
    {
        $this->authorizeApi('crew.requests.view', 'crew:read');
        Gate::authorize('view', $crewRequest);

        return $this->respondWith(CrewRequestData::fromModel($crewRequest)->toArray(), 'request');
    }

    public function store(CreateCrewRequestRequest $request, CreateCrewRequest $action): JsonResponse
    {
        $this->authorizeApi('crew.requests.create', 'crew:write');

        return $this->respondWith($action(CreateCrewRequestData::from($request->validated()))->toArray(), 'request', 201);
    }

    public function update(UpdateCrewRequestRequest $request, CrewRequest $crewRequest, UpdateCrewRequest $action): JsonResponse
    {
        $this->authorizeApi('crew.requests.edit', 'crew:write');

        return $this->respondWith($action($crewRequest, UpdateCrewRequestData::from($request->validated()))->toArray(), 'request', 200);
    }

    public function publish(Request $request, CrewRequest $crewRequest, PublishCrewRequest $action): JsonResponse
    {
        $this->authorizeApi('crew.requests.publish', 'crew:write');
        $result = $action($crewRequest);

        return $this->respondWith($result->toArray(), 'request');
    }

    public function cancel(CancelCrewRequestRequest $request, CrewRequest $crewRequest, CancelCrewRequest $action): JsonResponse
    {
        $this->authorizeApi('crew.requests.cancel', 'crew:write');
        $result = $action($crewRequest, CancelCrewRequestData::from($request->validated()));

        return $this->respondWith($result->toArray(), 'request');
    }

    public function addLine(AddCrewRequestLineRequest $request, CrewRequest $crewRequest, AddCrewRequestLine $action): JsonResponse
    {
        $this->authorizeApi('crew.requests.edit', 'crew:write');

        return $this->respondWith($action($crewRequest, AddCrewRequestLineData::from($request->validated()))->toArray(), 'line', 201);
    }
}
