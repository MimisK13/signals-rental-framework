<?php

declare(strict_types=1);

namespace App\Domain\Projects\Presentation\Http\Controllers\Api\V1;

use App\Domain\Projects\Actions\CreateProject;
use App\Domain\Projects\Actions\DeleteProject;
use App\Domain\Projects\Actions\UpdateProject;
use App\Domain\Projects\Data\CreateProjectData;
use App\Domain\Projects\Data\ProjectData;
use App\Domain\Projects\Data\UpdateProjectData;
use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Presentation\Http\Requests\CreateProjectRequest;
use App\Domain\Projects\Presentation\Http\Requests\UpdateProjectRequest;
use App\Http\Controllers\Api\Controller;
use App\Http\Traits\FiltersQueries;
use App\Http\Traits\ResourceActions;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ProjectController extends Controller
{
    use FiltersQueries, ResourceActions;

    /** @var list<string> */
    protected array $allowedFilters = ['name', 'status', 'member_id', 'venue_id', 'owned_by', 'starts_at', 'ends_at'];

    /** @var list<string> */
    protected array $allowedSorts = ['name', 'status', 'starts_at', 'ends_at', 'created_at'];

    /** @var list<string> */
    protected array $allowedIncludes = ['customer', 'venue', 'owner', 'opportunities', 'quotations'];

    protected function modelClass(): string
    {
        return Project::class;
    }

    protected function responseDataClass(): string
    {
        return ProjectData::class;
    }

    protected function createDataClass(): string
    {
        return CreateProjectData::class;
    }

    protected function updateDataClass(): string
    {
        return UpdateProjectData::class;
    }

    protected function createActionClass(): string
    {
        return CreateProject::class;
    }

    protected function updateActionClass(): string
    {
        return UpdateProject::class;
    }

    protected function deleteActionClass(): string
    {
        return DeleteProject::class;
    }

    protected function singularKey(): string
    {
        return 'project';
    }

    protected function pluralKey(): string
    {
        return 'projects';
    }

    protected function entityType(): string
    {
        return 'projects';
    }

    protected function permissions(): array
    {
        return ['view' => 'projects.view', 'create' => 'projects.create', 'edit' => 'projects.edit', 'delete' => 'projects.delete'];
    }

    protected function abilities(): array
    {
        return ['read' => 'projects:read', 'write' => 'projects:write'];
    }

    #[ApiResponse(status: 200, type: 'array{projects: list<array{id: int, name: string, member_id: int|null, venue_id: int|null, owned_by: int|null, status: string, starts_at: string|null, ends_at: string|null, opportunities_count: int, quotations_count: int}>, meta: array{total: int, per_page: int, page: int, view?: array{id: int, name: string}}}')]
    public function index(Request $request): JsonResponse
    {
        return $this->resourceIndex($request);
    }

    #[ApiResponse(status: 200, type: 'array{project: array{id: int, name: string, member_id: int|null, venue_id: int|null, owned_by: int|null, status: string, starts_at: string|null, ends_at: string|null, opportunities_count: int, quotations_count: int}}')]
    public function show(Request $request, Project $project): JsonResponse
    {
        return $this->resourceShow($request, $project);
    }

    #[ApiResponse(status: 201, type: 'array{project: array{id: int, name: string, member_id: int|null, venue_id: int|null, owned_by: int|null, status: string, starts_at: string|null, ends_at: string|null, opportunities_count: int, quotations_count: int}}')]
    public function store(CreateProjectRequest $request): JsonResponse
    {
        $result = (new CreateProject)(CreateProjectData::from($request->validated()));

        return response()->json(['project' => $result->toArray()], Response::HTTP_CREATED);
    }

    #[ApiResponse(status: 200, type: 'array{project: array{id: int, name: string, member_id: int|null, venue_id: int|null, owned_by: int|null, status: string, starts_at: string|null, ends_at: string|null, opportunities_count: int, quotations_count: int}}')]
    public function update(UpdateProjectRequest $request, Project $project): JsonResponse
    {
        $result = (new UpdateProject)($project, UpdateProjectData::from($request->validated()));

        return response()->json(['project' => $result->toArray()]);
    }

    #[ApiResponse(status: 204)]
    public function destroy(Project $project): JsonResponse
    {
        return $this->resourceDestroy($project);
    }
}
