<?php

declare(strict_types=1);

namespace App\Domain\Crew\Presentation\Http\Controllers\Api\V1\Crew;

use App\Domain\API\Presentation\Http\Concerns\FiltersQueries;
use App\Domain\API\Presentation\Http\Controllers\Controller;
use App\Domain\Crew\Actions\Registry\ArchiveCrewMember;
use App\Domain\Crew\Actions\Registry\CreateCrewMember;
use App\Domain\Crew\Actions\Registry\UpdateCrewMember;
use App\Domain\Crew\Data\Registry\CreateCrewMemberData;
use App\Domain\Crew\Data\Registry\CrewMemberData;
use App\Domain\Crew\Data\Registry\UpdateCrewMemberData;
use App\Domain\Crew\Models\CrewMember;
use App\Domain\Crew\Presentation\Http\Requests\Crew\CreateCrewMemberRequest;
use App\Domain\Crew\Presentation\Http\Requests\Crew\UpdateCrewMemberRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class CrewMemberController extends Controller
{
    use FiltersQueries;

    protected ?string $customFieldModule = 'CrewMember';

    protected array $allowedFilters = ['name', 'status', 'engagement_type'];

    protected array $allowedSorts = ['name', 'status', 'engagement_type', 'id', 'created_at'];

    public function index(Request $request): JsonResponse
    {
        $this->authorizeApi('crew.view', 'crew:read');
        $query = CrewMember::query();
        $query = $this->applySort($this->applyFilters($query, $request), $request)->orderBy('id');

        $paginator = $this->paginateQuery($query, $request);
        $items = $paginator->getCollection()->map(function (CrewMember $record): array {
            return CrewMemberData::fromModel($record)->toArray();
        })->all();

        return $this->respondWithCollection($items, 'members', $paginator);
    }

    public function show(CrewMember $crewMember): JsonResponse
    {
        $this->authorizeApi('crew.view', 'crew:read');
        Gate::authorize('view', $crewMember);

        return $this->respondWith(CrewMemberData::fromModel($crewMember)->toArray(), 'member');
    }

    public function store(CreateCrewMemberRequest $request, CreateCrewMember $action): JsonResponse
    {
        $this->authorizeApi('crew.create', 'crew:write');

        return $this->respondWith($action(CreateCrewMemberData::from($request->validated()))->toArray(), 'member', 201);
    }

    public function update(UpdateCrewMemberRequest $request, CrewMember $crewMember, UpdateCrewMember $action): JsonResponse
    {
        $this->authorizeApi('crew.edit', 'crew:write');

        return $this->respondWith($action($crewMember, UpdateCrewMemberData::from($request->validated()))->toArray(), 'member', 200);
    }

    public function destroy(Request $request, CrewMember $crewMember, ArchiveCrewMember $action): JsonResponse
    {
        $this->authorizeApi('crew.delete', 'crew:write');
        $result = $action($crewMember);

        return $this->respondWith($result->toArray(), 'member');
    }
}
