<?php

declare(strict_types=1);

namespace App\Domain\Communications\Presentation\Livewire;

use App\Domain\Communications\Services\CommunicationLogQuery;
use App\Domain\Communications\Services\CommunicationLogStatusRegistry;
use App\Models\Member;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
final class MemberCommunications extends Component
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

    public function render(
        CommunicationLogQuery $queryBuilder,
        CommunicationLogStatusRegistry $statusRegistry,
    ): ViewContract {
        return view('communications::members.communications', $this->with($queryBuilder, $statusRegistry));
    }
}
