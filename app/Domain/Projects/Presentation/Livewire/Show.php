<?php

declare(strict_types=1);

namespace App\Domain\Projects\Presentation\Livewire;

use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Opportunities\Models\Opportunity;
use App\Domain\Quotations\Models\Quotation;
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

    public function mount(Project $project): void
    {
        Gate::authorize('view', $project);
        $this->project = $project->load(['customer', 'venue', 'owner']);
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

    /** @return Collection<int, Quotation> */
    #[Computed]
    public function projectQuotations(): Collection
    {
        return $this->project->quotations()
            ->with('acceptedVersion')
            ->orderByDesc('created_at')
            ->get();
    }

    public function render(): View
    {
        return view('projects::show', [
            'opportunities' => $this->projectOpportunities(),
            'quotations' => $this->projectQuotations(),
        ]);
    }
}
