<?php

declare(strict_types=1);

namespace App\Domain\Projects\Presentation\Livewire;

use App\Domain\Projects\Actions\CreateProject;
use App\Domain\Projects\Actions\DeleteProject;
use App\Domain\Projects\Actions\UpdateProject;
use App\Domain\Projects\Data\CreateProjectData;
use App\Domain\Projects\Data\UpdateProjectData;
use App\Domain\Projects\Enums\ProjectStatus;
use App\Domain\Projects\Models\Project;
use App\Enums\MembershipType;
use App\Models\Member;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Projects')]
final class Index extends Component
{
    #[Url(as: 'archive')]
    public string $archiveFilter = 'active';

    #[Url(as: 'panel')]
    public ?string $panel = null;

    #[Url(as: 'project')]
    public ?int $panelProjectId = null;

    public ?int $editingProjectId = null;

    public string $projectName = '';

    public ?int $projectCustomerId = null;

    public ?int $projectVenueId = null;

    public ?int $projectOwnerId = null;

    public string $projectStatus = 'draft';

    public ?string $projectStartsAt = null;

    public ?string $projectEndsAt = null;

    public function mount(): void
    {
        Gate::authorize('viewAny', Project::class);

        if ($this->panel === 'edit' && $this->panelProjectId !== null) {
            $this->loadProjectForEdit($this->panelProjectId);
        }
    }

    public function setArchiveFilter(string $filter): void
    {
        if (in_array($filter, ['active', 'archived', 'all'], true)) {
            $this->archiveFilter = $filter;
        }
    }

    public function openCreateProject(): void
    {
        Gate::authorize('create', Project::class);
        $this->resetProjectForm();
        $this->panel = 'create';
        $this->panelProjectId = null;
        $this->dispatch('open-project-panel');
    }

    public function openEditProject(int $projectId): void
    {
        $this->loadProjectForEdit($projectId);
        $this->panel = 'edit';
        $this->panelProjectId = $projectId;
        $this->dispatch('open-project-panel');
    }

    public function saveProject(): void
    {
        $this->validate([
            'projectName' => ['required', 'string', 'max:255'],
            'projectCustomerId' => ['sometimes', 'nullable', 'integer', 'exists:members,id'],
            'projectVenueId' => ['sometimes', 'nullable', 'integer', 'exists:members,id'],
            'projectOwnerId' => ['sometimes', 'nullable', 'integer', 'exists:members,id'],
            'projectStatus' => ['required', 'string', 'in:'.implode(',', array_column(ProjectStatus::cases(), 'value'))],
            'projectStartsAt' => ['sometimes', 'nullable', 'date'],
            'projectEndsAt' => ['sometimes', 'nullable', 'date', 'after_or_equal:projectStartsAt'],
        ]);

        $wasEditing = $this->editingProjectId !== null;

        try {
            if ($wasEditing) {
                (new UpdateProject)(Project::query()->findOrFail($this->editingProjectId), UpdateProjectData::from([
                    'name' => $this->projectName,
                    'member_id' => $this->projectCustomerId,
                    'venue_id' => $this->projectVenueId,
                    'owned_by' => $this->projectOwnerId,
                    'status' => $this->projectStatus,
                    'starts_at' => $this->projectStartsAt,
                    'ends_at' => $this->projectEndsAt,
                ]));
            } else {
                (new CreateProject)(CreateProjectData::from([
                    'name' => $this->projectName,
                    'member_id' => $this->projectCustomerId,
                    'venue_id' => $this->projectVenueId,
                    'owned_by' => $this->projectOwnerId,
                    'status' => $this->projectStatus,
                    'starts_at' => $this->projectStartsAt,
                    'ends_at' => $this->projectEndsAt,
                ]));
            }
        } catch (ValidationException $exception) {
            $this->copyActionErrors($exception);

            return;
        }

        $this->closeProjectPanel();
        $this->dispatch('toast', type: 'success', message: $wasEditing ? 'Project updated.' : 'Project created.');
    }

    public function closeProjectPanel(): void
    {
        $this->resetValidation();
        $this->resetProjectForm();
        $this->panel = null;
        $this->panelProjectId = null;
        $this->dispatch('close-project-panel');
    }

    public function archive(int $projectId): void
    {
        (new DeleteProject)(Project::query()->findOrFail($projectId));
    }

    /**
     * @return array{customers: Collection<int, Member>, venues: Collection<int, Member>, owners: Collection<int, Member>}
     */
    #[Computed]
    public function panelOptions(): array
    {
        if ($this->panel === null) {
            return [
                'customers' => collect(),
                'venues' => collect(),
                'owners' => collect(),
            ];
        }

        return [
            'customers' => Member::query()->ofType(MembershipType::Organisation)->orderBy('name')->get(['id', 'name']),
            'venues' => Member::query()->ofType(MembershipType::Venue)->orderBy('name')->get(['id', 'name']),
            'owners' => Member::query()->ofType(MembershipType::User)->orderBy('name')->get(['id', 'name']),
        ];
    }

    public function render(): View
    {
        return view('projects::index', [
            ...$this->panelOptions(),
            'statuses' => ProjectStatus::cases(),
            'isEditing' => $this->editingProjectId !== null,
            'columns' => $this->columns(),
            'scopes' => $this->scopes(),
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function columns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Project', 'sortable' => true, 'filterable' => true, 'filter_type' => 'text', 'view' => 'projects::partials.column-name'],
            ['key' => 'customer_name', 'label' => 'Customer', 'view' => 'projects::partials.column-customer'],
            ['key' => 'venue_name', 'label' => 'Venue', 'view' => 'projects::partials.column-venue'],
            ['key' => 'status', 'label' => 'Status', 'sortable' => true, 'filterable' => true, 'filter_type' => 'select', 'filter_options' => collect(ProjectStatus::cases())->mapWithKeys(fn (ProjectStatus $status): array => [$status->value => $status->label()])->all(), 'view' => 'projects::partials.column-status'],
            ['key' => 'starts_at', 'label' => 'Dates', 'sortable' => true, 'view' => 'projects::partials.column-dates'],
            ['key' => 'owner_name', 'label' => 'Owner', 'view' => 'projects::partials.column-owner'],
            ['key' => 'opportunities_count', 'label' => 'Jobs', 'sortable' => true, 'view' => 'projects::partials.column-opportunities-count'],
            ['key' => 'actions', 'type' => 'actions'],
        ];
    }

    /** @return array<string, bool> */
    private function scopes(): array
    {
        return match ($this->archiveFilter) {
            'archived' => ['archived' => true],
            'all' => ['withArchived' => true],
            default => [],
        };
    }

    private function loadProjectForEdit(int $projectId): void
    {
        $project = Project::query()->findOrFail($projectId);
        Gate::authorize('update', $project);

        $this->editingProjectId = $project->id;
        $this->projectName = $project->name;
        $this->projectCustomerId = $project->member_id;
        $this->projectVenueId = $project->venue_id;
        $this->projectOwnerId = $project->owned_by;
        $this->projectStatus = $project->status->value;
        $this->projectStartsAt = $project->starts_at?->format('Y-m-d\TH:i');
        $this->projectEndsAt = $project->ends_at?->format('Y-m-d\TH:i');
    }

    private function resetProjectForm(): void
    {
        $this->editingProjectId = null;
        $this->projectName = '';
        $this->projectCustomerId = null;
        $this->projectVenueId = null;
        $this->projectOwnerId = null;
        $this->projectStatus = ProjectStatus::Draft->value;
        $this->projectStartsAt = null;
        $this->projectEndsAt = null;
    }

    private function copyActionErrors(ValidationException $exception): void
    {
        foreach ($exception->errors() as $field => $messages) {
            $componentField = match ($field) {
                'name' => 'projectName',
                'member_id' => 'projectCustomerId',
                'venue_id' => 'projectVenueId',
                'owned_by' => 'projectOwnerId',
                'status' => 'projectStatus',
                'starts_at' => 'projectStartsAt',
                'ends_at' => 'projectEndsAt',
                default => $field,
            };

            foreach ($messages as $message) {
                $this->addError($componentField, $message);
            }
        }
    }
}
