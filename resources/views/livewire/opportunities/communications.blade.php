<?php

use App\Livewire\Concerns\HasOpportunityActions;
use App\Models\CommunicationLog;
use App\Models\Opportunity;
use App\Services\Notifications\CommunicationLogQuery;
use App\Services\Notifications\CommunicationLogStatusRegistry;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.app')] class extends Component
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
}; ?>

<section class="w-full">
    @include('livewire.opportunities.partials.opportunity-header', ['opportunity' => $opportunity, 'subpage' => 'Communications', 'showActions' => true, 'canChangeStatus' => $canChangeStatus])
    @include('livewire.opportunities.partials.opportunity-tabs', ['opportunity' => $opportunity, 'activeTab' => 'communications'])

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3">
        @include('livewire.notifications.partials.communication-log-table', [
            'logs' => $logs,
            'statuses' => $statuses,
        ])

        <div class="mt-4">{{ $logs->links() }}</div>
    </div>
</section>
