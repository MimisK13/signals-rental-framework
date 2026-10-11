<?php

use App\Actions\Projects\CreateProject;
use App\Actions\Projects\DeleteProject;
use App\Actions\Projects\UpdateProject;
use App\Data\Projects\CreateProjectData;
use App\Data\Projects\UpdateProjectData;
use App\Enums\MembershipType;
use App\Enums\ProjectStatus;
use App\Models\Member;
use App\Models\Project;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Projects')] class extends Component
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
        $validated = $this->validate([
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
            if (! $wasEditing) {
                (new CreateProject)(CreateProjectData::from([
                    'name' => $this->projectName,
                    'member_id' => $this->projectCustomerId,
                    'venue_id' => $this->projectVenueId,
                    'owned_by' => $this->projectOwnerId,
                    'status' => $this->projectStatus,
                    'starts_at' => $this->projectStartsAt,
                    'ends_at' => $this->projectEndsAt,
                ]));
            } else {
                (new UpdateProject)(Project::query()->findOrFail($this->editingProjectId), UpdateProjectData::from([
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
        $project = Project::query()->findOrFail($projectId);
        (new DeleteProject)($project);
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            ...$this->panelOptions,
            'statuses' => ProjectStatus::cases(),
            'isEditing' => $this->editingProjectId !== null,
            'columns' => [
                ['key' => 'name', 'label' => 'Project', 'sortable' => true, 'filterable' => true, 'filter_type' => 'text', 'view' => 'livewire.projects.partials.column-name'],
                ['key' => 'customer_name', 'label' => 'Customer', 'view' => 'livewire.projects.partials.column-customer'],
                ['key' => 'venue_name', 'label' => 'Venue', 'view' => 'livewire.projects.partials.column-venue'],
                ['key' => 'status', 'label' => 'Status', 'sortable' => true, 'filterable' => true, 'filter_type' => 'select', 'filter_options' => collect(ProjectStatus::cases())->mapWithKeys(fn (ProjectStatus $status): array => [$status->value => $status->label()])->all(), 'view' => 'livewire.projects.partials.column-status'],
                ['key' => 'starts_at', 'label' => 'Dates', 'sortable' => true, 'view' => 'livewire.projects.partials.column-dates'],
                ['key' => 'owner_name', 'label' => 'Owner', 'view' => 'livewire.projects.partials.column-owner'],
                ['key' => 'opportunities_count', 'label' => 'Jobs', 'sortable' => true, 'view' => 'livewire.projects.partials.column-opportunities-count'],
                ['key' => 'actions', 'type' => 'actions'],
            ],
            'scopes' => match ($this->archiveFilter) {
                'archived' => ['archived' => true],
                'all' => ['withArchived' => true],
                default => [],
            },
        ];
    }

    /**
     * Load select options once per Livewire request while the panel is open.
     *
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
        $this->projectStartsAt = $project->starts_at?->format('Y-m-d\\TH:i');
        $this->projectEndsAt = $project->ends_at?->format('Y-m-d\\TH:i');
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
};
?>

<section
    class="relative min-w-0 w-full"
    x-data="{ projectPanelOpen: @js($panel !== null) }"
    x-on:open-project-panel.window="projectPanelOpen = true"
    x-on:close-project-panel.window="projectPanelOpen = false"
>
    <x-dense::page-header title="Projects">
        <x-slot:actions>
            @can('create', App\Models\Project::class)
                <x-dense::button
                    type="button"
                    variant="accent"
                    size="sm"
                    wire:click="openCreateProject"
                    x-on:click="$dispatch('open-project-panel')"
                >
                    New Project
                </x-dense::button>
            @endcan
        </x-slot:actions>
    </x-dense::page-header>

    <div class="flex min-w-0 flex-1 items-stretch max-md:flex-col" :class="{ 'pr-[360px] max-md:pr-0': projectPanelOpen }">
        <div class="min-w-0 flex-1 px-6 pb-6 max-md:px-5 max-sm:px-3">
            <livewire:components.data-table
                :columns="$columns"
                :model="\App\Models\Project::class"
                :searchable="['name']"
                :with="['customer', 'venue', 'owner']"
                :with-counts="['opportunities']"
                :scopes="$scopes"
                default-sort="-starts_at"
                empty-message="No projects found."
                actions-view="livewire.projects.partials.row-actions"
                :key="'projects-table-'.$archiveFilter"
            />

            {{--
                @if($this->projects->isEmpty())
                    <x-dense::empty-state title="No projects" description="Create a Project to group related opportunities across Stores." />
                @else
                    <div class="overflow-x-auto">
                        <x-dense::table compact>
                            <thead>
                                <tr>
                                    <th>Project</th>
                                    <th>Customer</th>
                                    <th>Venue</th>
                                    <th>Status</th>
                                    <th>Dates</th>
                                    <th>Owner</th>
                                    <th>Jobs</th>
                                    <th class="text-center">
                                        <span class="sr-only">Actions</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($this->projects as $project)
                                    <tr wire:key="project-{{ $project->id }}">
                                        <td>
                                            <a href="{{ route('projects.show', $project) }}" wire:navigate class="font-medium hover:underline">
                                                {{ $project->name }}
                                            </a>
                                        </td>
                                        <td>{{ $project->customer?->name ?? '—' }}</td>
                                        <td>{{ $project->venue?->name ?? '—' }}</td>
                                        <td>
                                            <x-dense::status
                                                variant="outline"
                                                :color="match($project->status->value) { 'active' => 'green', 'completed' => 'blue', 'archived' => 'zinc', default => 'amber' }"
                                                :label="$project->status->label()"
                                            />
                                        </td>
                                        <td class="whitespace-nowrap">
                                            {{ $project->starts_at?->format('d M Y') ?? '—' }}{{ $project->ends_at ? ' – '.$project->ends_at->format('d M Y') : '' }}
                                        </td>
                                        <td>{{ $project->owner?->name ?? '—' }}</td>
                                        <td>{{ $project->opportunities_count }}</td>
                                        <td class="text-center">
                                            @if(!$project->trashed())
                                                <div class="flex justify-center">
                                                    <x-dense::dropdown menu floating min-width="150px">
                                                        <x-slot:trigger>
                                                        <button
                                                            x-ref="trigger"
                                                            x-on:click.stop="toggle()"
                                                            :aria-expanded="open"
                                                            :aria-controls="$id('dense-dropdown')"
                                                            type="button"
                                                            class="s-btn-ghost s-btn-xs s-btn-icon"
                                                            aria-label="Project actions"
                                                            title="Project actions"
                                                        >
                                                            <x-dense::icons.ellipsis-horizontal size="5" :outline="false" solid aria-hidden="true" />
                                                        </button>
                                                        </x-slot:trigger>
                                                        <x-dense::dropdown.item menu wire:click="openEditProject({{ $project->id }})" x-on:click="close()">
                                                            Edit
                                                        </x-dense::dropdown.item>
                                                        <x-dense::dropdown.item menu wire:click="archive({{ $project->id }})" wire:confirm="Archive {{ $project->name }}?" x-on:click="close()">
                                                            Archive
                                                        </x-dense::dropdown.item>
                                                    </x-dense::dropdown>
                                                </div>
                                            @else
                                                <span class="text-[var(--text-muted)]">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </x-dense::table>
                    </div>
                @endif
            --}}
        </div>

        <x-dense::side-panel
            x-show="projectPanelOpen"
            x-cloak
            mode="viewport"
            :title="$isEditing ? 'Edit Project' : 'New Project'"
            close="$wire.closeProjectPanel(); projectPanelOpen = false"
        >
            <form id="project-panel-form" wire:submit="saveProject" class="space-y-4">
                <x-dense::field label="Project name" for="project-panel-name" required :error="$errors->first('projectName')" error-id="project-panel-name-error">
                    <x-dense::input
                        id="project-panel-name"
                        wire:model="projectName"
                        required
                        autocomplete="off"
                        :invalid="$errors->has('projectName')"
                        :aria-describedby="$errors->has('projectName') ? 'project-panel-name-error' : null"
                    />
                </x-dense::field>

                @if($isEditing)
                    <x-dense::field label="Status" for="project-panel-status" :error="$errors->first('projectStatus')" error-id="project-panel-status-error">
                        <x-dense::select id="project-panel-status" wire:model="projectStatus" :invalid="$errors->has('projectStatus')" :aria-describedby="$errors->has('projectStatus') ? 'project-panel-status-error' : null">
                            @foreach($statuses as $projectStatus)
                                <option value="{{ $projectStatus->value }}">{{ $projectStatus->label() }}</option>
                            @endforeach
                        </x-dense::select>
                    </x-dense::field>
                @endif

                <x-dense::field label="Customer" for="project-panel-customer" :error="$errors->first('projectCustomerId')" error-id="project-panel-customer-error">
                    <x-dense::select id="project-panel-customer" wire:model="projectCustomerId" :invalid="$errors->has('projectCustomerId')" :aria-describedby="$errors->has('projectCustomerId') ? 'project-panel-customer-error' : null">
                        <option value="">None</option>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                        @endforeach
                    </x-dense::select>
                </x-dense::field>

                <x-dense::field label="Primary venue" for="project-panel-venue" :error="$errors->first('projectVenueId')" error-id="project-panel-venue-error">
                    <x-dense::select id="project-panel-venue" wire:model="projectVenueId" :invalid="$errors->has('projectVenueId')" :aria-describedby="$errors->has('projectVenueId') ? 'project-panel-venue-error' : null">
                        <option value="">None</option>
                        @foreach($venues as $venue)
                            <option value="{{ $venue->id }}">{{ $venue->name }}</option>
                        @endforeach
                    </x-dense::select>
                </x-dense::field>

                <x-dense::field label="Owner" for="project-panel-owner" :error="$errors->first('projectOwnerId')" error-id="project-panel-owner-error">
                    <x-dense::select id="project-panel-owner" wire:model="projectOwnerId" :invalid="$errors->has('projectOwnerId')" :aria-describedby="$errors->has('projectOwnerId') ? 'project-panel-owner-error' : null">
                        <option value="">None</option>
                        @foreach($owners as $owner)
                            <option value="{{ $owner->id }}">{{ $owner->name }}</option>
                        @endforeach
                    </x-dense::select>
                </x-dense::field>

                <div class="grid gap-3 sm:grid-cols-2">
                    <x-dense::field label="Starts" for="project-panel-starts" :error="$errors->first('projectStartsAt')" error-id="project-panel-starts-error">
                        <x-dense::input id="project-panel-starts" type="datetime-local" wire:model="projectStartsAt" :invalid="$errors->has('projectStartsAt')" :aria-describedby="$errors->has('projectStartsAt') ? 'project-panel-starts-error' : null" />
                    </x-dense::field>
                    <x-dense::field label="Ends" for="project-panel-ends" :error="$errors->first('projectEndsAt')" error-id="project-panel-ends-error">
                        <x-dense::input id="project-panel-ends" type="datetime-local" wire:model="projectEndsAt" :invalid="$errors->has('projectEndsAt')" :aria-describedby="$errors->has('projectEndsAt') ? 'project-panel-ends-error' : null" />
                    </x-dense::field>
                </div>
            </form>

            <x-slot:footer>
                <div class="dense-side-panel-footer-actions">
                    <x-dense::button type="button" variant="ghost" size="sm" wire:click="closeProjectPanel">Cancel</x-dense::button>
                    <x-dense::button type="submit" form="project-panel-form" variant="accent" size="sm" wire:loading.attr="disabled" wire:target="saveProject">
                        <span wire:loading.remove wire:target="saveProject">{{ $isEditing ? 'Save Changes' : 'Create Project' }}</span>
                        <span wire:loading wire:target="saveProject">{{ $isEditing ? 'Saving…' : 'Creating…' }}</span>
                    </x-dense::button>
                </div>
            </x-slot:footer>
        </x-dense::side-panel>
    </div>
</section>
