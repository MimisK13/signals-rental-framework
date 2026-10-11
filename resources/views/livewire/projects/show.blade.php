<?php

use App\Actions\Projects\AttachOpportunityToProject;
use App\Actions\Projects\DetachOpportunityFromProject;
use App\Actions\Projects\MoveOpportunityToProject;
use App\Models\Opportunity;
use App\Models\Project;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Project')] class extends Component
{
    public Project $project;

    public ?int $selectedOpportunityId = null;

    public ?int $moveOpportunityId = null;

    public ?int $moveDestinationId = null;

    public function mount(Project $project): void
    {
        Gate::authorize('view', $project);
        $this->project = $project;
    }

    public function attach(): void
    {
        $this->validate(['selectedOpportunityId' => ['required', 'integer', 'exists:opportunities,id']]);
        (new AttachOpportunityToProject)($this->project, Opportunity::query()->findOrFail($this->selectedOpportunityId));
        $this->selectedOpportunityId = null;
        $this->project->refresh();
    }

    public function detach(int $opportunityId): void
    {
        (new DetachOpportunityFromProject)($this->project, Opportunity::query()->findOrFail($opportunityId));
        $this->project->refresh();
    }

    public function move(): void
    {
        $this->validate([
            'moveOpportunityId' => ['required', 'integer', 'exists:opportunities,id'],
            'moveDestinationId' => ['required', 'integer', 'exists:projects,id'],
        ]);

        (new MoveOpportunityToProject)(Project::query()->findOrFail($this->moveDestinationId), Opportunity::query()->findOrFail($this->moveOpportunityId));
        $this->moveOpportunityId = null;
        $this->moveDestinationId = null;
        $this->project->refresh();
    }

    /** @return array<string, mixed> */
    public function with(): array
    {
        return [
            'opportunities' => $this->projectOpportunities,
            'unassignedOpportunities' => $this->unassignedOpportunities,
            'otherProjects' => $this->otherProjects,
        ];
    }

    /** @return Collection<int, Opportunity> */
    #[Computed]
    public function projectOpportunities(): Collection
    {
        return $this->project->opportunities()
            ->with(['member', 'store'])
            ->orderByDesc('starts_at')
            ->get();
    }

    /** @return Collection<int, Opportunity> */
    #[Computed]
    public function unassignedOpportunities(): Collection
    {
        return Opportunity::query()
            ->whereNull('project_id')
            ->with('member')
            ->orderByDesc('starts_at')
            ->limit(100)
            ->get();
    }

    /** @return Collection<int, Project> */
    #[Computed]
    public function otherProjects(): Collection
    {
        return Project::query()
            ->where('id', '!=', $this->project->id)
            ->orderBy('name')
            ->get(['id', 'name']);
    }
};
?>

<div class="min-w-0">
    <x-dense::page-header :title="$project->name">
        <x-slot:meta>
            <x-dense::status
                variant="outline"
                :color="match($project->status->value) { 'active' => 'green', 'completed' => 'blue', 'archived' => 'zinc', default => 'amber' }"
                :label="$project->status->label()"
            />
            <span>{{ $project->opportunities()->count() }} jobs</span>
        </x-slot:meta>
        <x-slot:actions>
            @can('update', $project)
                <x-dense::button :href="route('projects.edit', $project)" variant="secondary" size="sm" wire:navigate>
                    Edit
                </x-dense::button>
            @endcan
        </x-slot:actions>
    </x-dense::page-header>

    <div class="grid gap-4 px-6 pb-6 xl:grid-cols-[minmax(0,1fr)_320px]">
        <x-dense::table-card title="Jobs / Rentals" :count="$opportunities->count()">
            @if($opportunities->isEmpty())
                <x-dense::empty-state title="No jobs assigned" description="Attach an existing Opportunity without changing its Store or operational dates." />
            @else
                <div class="overflow-x-auto">
                    <table class="s-table w-full">
                        <thead><tr><th>Job</th><th>Customer</th><th>Store</th><th>Dates</th><th class="text-right">Actions</th></tr></thead>
                        <tbody>
                            @foreach($opportunities as $opportunity)
                                <tr wire:key="project-opportunity-{{ $opportunity->id }}">
                                    <td><a href="{{ route('opportunities.show', $opportunity) }}" wire:navigate class="font-medium hover:underline">{{ $opportunity->subject }}</a></td>
                                    <td>{{ $opportunity->member?->name ?? '—' }}</td>
                                    <td>{{ $opportunity->store?->name ?? '—' }}</td>
                                    <td>{{ $opportunity->starts_at?->format('d M Y') ?? '—' }}</td>
                                    <td class="text-right">
                                        <x-dense::button type="button" variant="ghost" size="sm" wire:click="detach({{ $opportunity->id }})" wire:confirm="Detach this Opportunity from the Project?">Detach</x-dense::button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-dense::table-card>

        <div class="space-y-4">
            <x-dense::card title="Attach job">
                <form wire:submit="attach" class="space-y-3">
                    <x-dense::field label="Unassigned Opportunity" for="project-attach-opportunity" :error="$errors->first('selectedOpportunityId')" error-id="project-attach-opportunity-error">
                        <x-dense::select id="project-attach-opportunity" wire:model="selectedOpportunityId" :invalid="$errors->has('selectedOpportunityId')" :aria-describedby="$errors->has('selectedOpportunityId') ? 'project-attach-opportunity-error' : null">
                            <option value="">Select an opportunity</option>
                            @foreach($unassignedOpportunities as $opportunity)
                                <option value="{{ $opportunity->id }}">{{ $opportunity->subject }}</option>
                            @endforeach
                        </x-dense::select>
                    </x-dense::field>
                    <x-dense::button type="submit" variant="primary" size="sm">Attach</x-dense::button>
                </form>
            </x-dense::card>

            <x-dense::card title="Move job">
                <form wire:submit="move" class="space-y-3">
                    <x-dense::field label="Opportunity" for="project-move-opportunity" :error="$errors->first('moveOpportunityId')" error-id="project-move-opportunity-error">
                        <x-dense::select id="project-move-opportunity" wire:model="moveOpportunityId" :invalid="$errors->has('moveOpportunityId')" :aria-describedby="$errors->has('moveOpportunityId') ? 'project-move-opportunity-error' : null">
                            <option value="">Select an opportunity</option>
                            @foreach($opportunities as $opportunity)
                                <option value="{{ $opportunity->id }}">{{ $opportunity->subject }}</option>
                            @endforeach
                        </x-dense::select>
                    </x-dense::field>
                    <x-dense::field label="Destination Project" for="project-move-destination" :error="$errors->first('moveDestinationId')" error-id="project-move-destination-error">
                        <x-dense::select id="project-move-destination" wire:model="moveDestinationId" :invalid="$errors->has('moveDestinationId')" :aria-describedby="$errors->has('moveDestinationId') ? 'project-move-destination-error' : null">
                            <option value="">Select a project</option>
                            @foreach($otherProjects as $otherProject)
                                <option value="{{ $otherProject->id }}">{{ $otherProject->name }}</option>
                            @endforeach
                        </x-dense::select>
                    </x-dense::field>
                    <x-dense::button type="submit" variant="secondary" size="sm">Move</x-dense::button>
                </form>
            </x-dense::card>
        </div>
    </div>
</div>
