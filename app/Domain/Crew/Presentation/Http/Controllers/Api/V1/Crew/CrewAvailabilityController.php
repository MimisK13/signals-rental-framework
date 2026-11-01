<?php

declare(strict_types=1);

namespace App\Domain\Crew\Presentation\Http\Controllers\Api\V1\Crew;

use App\Domain\Crew\Actions\Availability\ApproveCrewAvailability;
use App\Domain\Crew\Actions\Availability\CancelCrewAvailability;
use App\Domain\Crew\Actions\Availability\RejectCrewAvailability;
use App\Domain\Crew\Actions\Availability\RequestCrewAvailability;
use App\Domain\Crew\Data\Availability\CrewAvailabilityData;
use App\Domain\Crew\Data\Availability\RejectCrewAvailabilityData;
use App\Domain\Crew\Data\Availability\RequestCrewAvailabilityData;
use App\Domain\Crew\Models\CrewAvailability;
use App\Domain\Crew\Models\CrewMember;
use App\Domain\Crew\Presentation\Http\Requests\Crew\RejectCrewAvailabilityRequest;
use App\Domain\Crew\Presentation\Http\Requests\Crew\RequestCrewAvailabilityRequest;
use App\Http\Controllers\Api\Controller;
use App\Http\Traits\FiltersQueries;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class CrewAvailabilityController extends Controller
{
    use FiltersQueries;

    protected array $allowedFilters = ['crew_member_id', 'status', 'starts_at', 'ends_at'];

    protected array $allowedSorts = ['crew_member_id', 'status', 'starts_at', 'ends_at', 'id', 'created_at'];

    public function index(Request $request): JsonResponse
    {
        $this->authorizeApi('crew.availability.view', 'crew:read');
        $query = CrewAvailability::query();
        $query = $this->applySort($this->applyFilters($query, $request), $request)->orderBy('id');

        $paginator = $this->paginateQuery($query, $request);
        $items = $paginator->getCollection()->map(function (CrewAvailability $record): array {
            return CrewAvailabilityData::fromModel($record)->toArray();
        })->all();

        return $this->respondWithCollection($items, 'availabilities', $paginator);
    }

    public function show(CrewAvailability $crewAvailability): JsonResponse
    {
        $this->authorizeApi('crew.availability.view', 'crew:read');
        Gate::authorize('view', $crewAvailability);

        return $this->respondWith(CrewAvailabilityData::fromModel($crewAvailability)->toArray(), 'availability');
    }

    public function store(RequestCrewAvailabilityRequest $request, CrewMember $crewMember, RequestCrewAvailability $action): JsonResponse
    {
        $this->authorizeApi('crew.availability.request', 'crew:write');

        return $this->respondWith($action($crewMember, RequestCrewAvailabilityData::from($request->validated()))->toArray(), 'availability', 201);
    }

    public function approve(Request $request, CrewAvailability $crewAvailability, ApproveCrewAvailability $action): JsonResponse
    {
        $this->authorizeApi('crew.availability.approve', 'crew:write');
        $result = $action($crewAvailability);

        return $this->respondWith($result->toArray(), 'availability');
    }

    public function reject(RejectCrewAvailabilityRequest $request, CrewAvailability $crewAvailability, RejectCrewAvailability $action): JsonResponse
    {
        $this->authorizeApi('crew.availability.approve', 'crew:write');
        $result = $action($crewAvailability, RejectCrewAvailabilityData::from($request->validated()));

        return $this->respondWith($result->toArray(), 'availability');
    }

    public function cancel(Request $request, CrewAvailability $crewAvailability, CancelCrewAvailability $action): JsonResponse
    {
        $this->authorizeTokenAbility('crew:write');
        $result = $action($crewAvailability);

        return $this->respondWith($result->toArray(), 'availability');
    }
}
