<?php

use App\Models\CommunicationLog;
use App\Models\Member;
use App\Services\Notifications\CommunicationLogQuery;
use App\Services\Notifications\CommunicationLogStatusRegistry;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.app')] class extends Component
{
    use WithPagination;

    public Member $member;

    public function mount(Member $member): void
    {
        Gate::authorize('members.access');

        $this->member = $member;
    }

    public function rendering(View $view): void
    {
        $view->title($this->member->name.' — Communications');
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
            ->forSubject(Member::class, $this->member->id, $user)
            ->with(['notifiable'])
            ->latest('created_at')
            ->paginate(25);

        return [
            'logs' => $logs,
            'statuses' => $statusRegistry->statuses(),
        ];
    }
}; ?>

<section class="w-full">
    @include('livewire.members.partials.member-header', ['member' => $member])
    @include('livewire.members.partials.member-tabs', ['member' => $member, 'activeTab' => 'communications'])

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3">
        @include('livewire.notifications.partials.communication-log-table', [
            'logs' => $logs,
            'statuses' => $statuses,
        ])

        <div class="mt-4">{{ $logs->links() }}</div>
    </div>
</section>
