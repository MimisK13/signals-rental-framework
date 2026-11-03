<?php

declare(strict_types=1);

namespace App\Domain\Projects\Opportunities\Presentation\Livewire;

use App\Domain\Projects\Opportunities\Models\Opportunity;
use App\Domain\Projects\Opportunities\Presentation\Concerns\HasOpportunityActions;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Read-only costs listing for an opportunity (M8-2).
 */
#[Layout('components.layouts.app')] final class Costs extends Component
{
    use HasOpportunityActions;

    public Opportunity $opportunity;

    public function mount(Opportunity $opportunity): void
    {
        Gate::authorize('opportunities.view');

        $this->opportunity = $opportunity->load(['costs.virtualStockIntake']);
    }

    public function rendering(View $view): void
    {
        $view->title($this->opportunity->subject.' — Costs');
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return $this->opportunityActionData();
    }

    public function render(): View
    {
        return view('project-opportunities::costs');
    }
}
