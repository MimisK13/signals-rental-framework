<?php

declare(strict_types=1);

namespace App\Domain\Communications\Presentation\Livewire;

use App\Domain\Communications\Services\CommunicationLogQuery;
use App\Domain\Communications\Services\CommunicationLogStatusRegistry;
use App\Livewire\Concerns\HasOpportunityActions;
use App\Models\Opportunity;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
final class OpportunityCommunications extends Component
{
    use HasOpportunityActions;
    use WithPagination;

    public Opportunity $opportunity;

    public function mount(Opportunity $opportunity): void
    {
        Gate::authorize('opportunities.view');

        $this->opportunity = $opportunity;
    }

    public function rendering(View $view): void
    {
        $view->title($this->opportunity->subject.' — Communications');
    }

    /**
     * @return array<string, mixed>
     */
    public function with(
        CommunicationLogQuery $queryBuilder,
        CommunicationLogStatusRegistry $statusRegistry,
    ): array {
        $user = auth()->user();

        $logs = $queryBuilder
            ->forSubject(Opportunity::class, $this->opportunity->id, $user)
            ->with(['notifiable'])
            ->latest('created_at')
            ->paginate(25);

        return array_merge($this->opportunityActionData(), [
            'logs' => $logs,
            'statuses' => $statusRegistry->statuses(),
        ]);
    }

    public function render(
        CommunicationLogQuery $queryBuilder,
        CommunicationLogStatusRegistry $statusRegistry,
    ): ViewContract {
        return view('communications::opportunities.communications', $this->with($queryBuilder, $statusRegistry));
    }
}
