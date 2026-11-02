<?php

declare(strict_types=1);

namespace App\Domain\Communications\Presentation\Livewire;

use App\Domain\Communications\Actions\ToggleCommunicationLogPin;
use App\Domain\Communications\Models\CommunicationLog as CommunicationLogModel;
use App\Domain\Communications\Services\CommunicationLogQuery;
use App\Domain\Communications\Services\CommunicationLogStatusRegistry;
use App\Models\Member;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')] #[Title('Communication Log')]
final class CommunicationLog extends Component
{
    use WithPagination;

    #[Url]
    public string $filterStatus = '';

    #[Url]
    public string $filterChannel = '';

    #[Url]
    public string $filterType = '';

    #[Url]
    public string $filterRecipient = '';

    #[Url]
    public string $filterDateFrom = '';

    #[Url]
    public string $filterDateTo = '';

    #[Url]
    public string $sortField = 'created_at';

    #[Url]
    public string $sortDirection = 'desc';

    public ?int $selectedLogId = null;

    public bool $showDetailModal = false;

    public function mount(): void
    {
        Gate::authorize('viewAny', CommunicationLogModel::class);
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatedFilterChannel(): void
    {
        $this->resetPage();
    }

    public function updatedFilterType(): void
    {
        $this->resetPage();
    }

    public function updatedFilterRecipient(): void
    {
        $this->resetPage();
    }

    public function updatedFilterDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedFilterDateTo(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }

    public function showDetail(int $logId): void
    {
        $log = CommunicationLogModel::query()->findOrFail($logId);
        Gate::authorize('view', $log);
        $this->selectedLogId = $logId;
        $this->showDetailModal = true;
    }

    public function closeDetail(): void
    {
        $this->selectedLogId = null;
        $this->showDetailModal = false;
    }

    public function togglePin(int $logId): void
    {
        $log = CommunicationLogModel::query()->findOrFail($logId);
        $user = auth()->user();

        if ($user === null) {
            return;
        }

        (new ToggleCommunicationLogPin)($user, $log);
        $this->dispatch('toast', type: 'success', message: __('Pin updated.'));
    }

    public function clearFilters(): void
    {
        $this->filterStatus = '';
        $this->filterChannel = '';
        $this->filterType = '';
        $this->filterRecipient = '';
        $this->filterDateFrom = '';
        $this->filterDateTo = '';
        $this->resetPage();
    }

    /**
     * @return array<string, mixed>
     */
    public function with(
        CommunicationLogQuery $queryBuilder,
        CommunicationLogStatusRegistry $statusRegistry,
    ): array {
        $user = auth()->user();

        $query = $queryBuilder->forUser($user)
            ->with(['subject', 'notifiable']);

        if ($this->filterStatus !== '') {
            $query->where('status', $this->filterStatus);
        }

        if ($this->filterChannel !== '') {
            $query->where('channel', $this->filterChannel);
        }

        if ($this->filterType !== '') {
            $query->where('notification_type_key', 'like', '%'.$this->filterType.'%');
        }

        if ($this->filterRecipient !== '') {
            $term = '%'.$this->filterRecipient.'%';
            $query->where(function ($builder) use ($term): void {
                $builder
                    ->where('recipient_address', 'like', $term)
                    ->orWhereHasMorph('notifiable', [Member::class], fn ($q) => $q->where('name', 'like', $term));
            });
        }

        if ($this->filterDateFrom !== '') {
            $query->where('created_at', '>=', $this->filterDateFrom);
        }

        if ($this->filterDateTo !== '') {
            $query->where('created_at', '<=', $this->filterDateTo.' 23:59:59');
        }

        $allowedSorts = ['created_at', 'status', 'channel', 'notification_type_key'];

        if (in_array($this->sortField, $allowedSorts, true)) {
            $query->orderBy($this->sortField, $this->sortDirection === 'asc' ? 'asc' : 'desc');
        }

        $selectedLog = $this->selectedLogId !== null
            ? CommunicationLogModel::query()->with(['subject', 'notifiable'])->find($this->selectedLogId)
            : null;

        if ($selectedLog !== null) {
            Gate::authorize('view', $selectedLog);
        }

        return [
            'logs' => $query->paginate(25),
            'statuses' => $statusRegistry->statuses(),
            'selectedLog' => $selectedLog,
            'timeline' => $selectedLog !== null ? $this->timelineFor($selectedLog) : [],
        ];
    }

    /**
     * @return list<array{label: string, at: ?string}>
     */
    private function timelineFor(CommunicationLogModel $log): array
    {
        $entries = [
            ['label' => 'Queued', 'at' => $log->queued_at?->toIso8601String()],
            ['label' => 'Sent', 'at' => $log->sent_at?->toIso8601String()],
            ['label' => 'Delivered', 'at' => $log->delivered_at?->toIso8601String()],
            ['label' => 'Opened', 'at' => $log->opened_at?->toIso8601String()],
        ];

        return array_values(array_filter(
            $entries,
            static fn (array $entry): bool => $entry['at'] !== null || $entry['label'] === 'Queued',
        ));
    }

    public function render(
        CommunicationLogQuery $queryBuilder,
        CommunicationLogStatusRegistry $statusRegistry,
    ): View {
        return view('communications::admin.communication-log', $this->with($queryBuilder, $statusRegistry));
    }
}
