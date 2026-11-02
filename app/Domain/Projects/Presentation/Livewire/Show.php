<?php

declare(strict_types=1);

namespace App\Domain\Projects\Presentation\Livewire;

use App\Domain\Projects\Actions\AttachOpportunityToProject;
use App\Domain\Projects\Actions\DetachOpportunityFromProject;
use App\Domain\Projects\Actions\MoveOpportunityToProject;
use App\Domain\Projects\Models\Project;
use App\Models\Opportunity;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Project')]
final class Show extends Component
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

        $destination = Project::query()->findOrFail($this->moveDestinationId);
        $opportunity = Opportunity::query()->findOrFail($this->moveOpportunityId);
        (new MoveOpportunityToProject)($destination, $opportunity);
        $this->moveOpportunityId = null;
        $this->moveDestinationId = null;
        $this->project->refresh();
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

    public function render(): View
    {
        return view('projects::show', [
            'opportunities' => $this->projectOpportunities(),
            'unassignedOpportunities' => $this->unassignedOpportunities(),
            'otherProjects' => $this->otherProjects(),
        ]);
    }
}
