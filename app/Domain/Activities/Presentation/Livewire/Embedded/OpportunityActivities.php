<?php

declare(strict_types=1);

namespace App\Domain\Activities\Presentation\Livewire\Embedded;

use App\Domain\Activities\Presentation\Livewire\Concerns\HasActivityActions;
use App\Livewire\Concerns\HasOpportunityActions;
use App\Models\Opportunity;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
final class OpportunityActivities extends Component
{
    use HasActivityActions;
    use HasOpportunityActions;

    public Opportunity $opportunity;

    public function mount(Opportunity $opportunity): void
    {
        Gate::authorize('opportunities.view');

        $this->opportunity = $opportunity;
    }

    public function rendering(View $view): void
    {
        $view->title($this->opportunity->subject.' — Activities');
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return array_merge($this->opportunityActionData(), [
            'columns' => $this->activityColumns(),
            'scopes' => [
                'forOpportunity' => $this->opportunity->id,
            ],
        ]);
    }

    public function render(): View
    {
        return view('activities::embedded.opportunities', $this->with());
    }
}
