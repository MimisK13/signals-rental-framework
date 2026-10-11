<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Projects\AttachOpportunityToProject;
use App\Actions\Projects\CreateProject;
use App\Actions\Projects\DeleteProject;
use App\Actions\Projects\DetachOpportunityFromProject;
use App\Actions\Projects\MoveOpportunityToProject;
use App\Actions\Projects\UpdateProject;
use App\Data\Projects\CreateProjectData;
use App\Data\Projects\ProjectData;
use App\Data\Projects\UpdateProjectData;
use App\Http\Controllers\Api\Controller;
use App\Http\Traits\FiltersQueries;
use App\Http\Traits\ResourceActions;
use App\Models\Opportunity;
use App\Models\Project;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    use FiltersQueries, ResourceActions;

    /** @var list<string> */
    protected array $allowedFilters = ['name', 'status', 'member_id', 'venue_id', 'owned_by', 'starts_at', 'ends_at'];

    /** @var list<string> */
    protected array $allowedSorts = ['name', 'status', 'starts_at', 'ends_at', 'created_at'];

    /** @var list<string> */
    protected array $allowedIncludes = ['customer', 'venue', 'owner', 'opportunities'];

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

    public function index(Request $request): JsonResponse
    {
        return $this->resourceIndex($request);
    }

    public function show(Request $request, Project $project): JsonResponse
    {
        return $this->resourceShow($request, $project);
    }

    public function store(Request $request): JsonResponse
    {
        return $this->resourceStore($request);
    }

    public function update(Request $request, Project $project): JsonResponse
    {
        return $this->resourceUpdate($request, $project);
    }

    public function destroy(Project $project): JsonResponse
    {
        return $this->resourceDestroy($project);
    }

    #[ApiResponse(204, 'Opportunity attached to project')]
    public function attach(Request $request, Project $project, Opportunity $opportunity): JsonResponse
    {
        $this->authorizeApi('projects.edit', 'projects:write');
        (new AttachOpportunityToProject)($project, $opportunity);

        return response()->json(null, 204);
    }

    public function detach(Request $request, Project $project, Opportunity $opportunity): JsonResponse
    {
        $this->authorizeApi('projects.edit', 'projects:write');
        (new DetachOpportunityFromProject)($project, $opportunity);

        return response()->json(null, 204);
    }

    public function move(Request $request, Project $project, Opportunity $opportunity): JsonResponse
    {
        $this->authorizeApi('projects.edit', 'projects:write');
        (new MoveOpportunityToProject)($project, $opportunity);

        return response()->json(null, 204);
    }
}
