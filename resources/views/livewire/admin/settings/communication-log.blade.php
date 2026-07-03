<?php

use App\Actions\Notifications\ToggleCommunicationLogPin;
use App\Models\CommunicationLog;
use App\Models\Member;
use App\Services\Notifications\CommunicationLogQuery;
use App\Services\Notifications\CommunicationLogStatusRegistry;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.app')] #[Title('Communication Log')] class extends Component
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
        Gate::authorize('viewAny', CommunicationLog::class);
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
        $log = CommunicationLog::query()->findOrFail($logId);
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
        $log = CommunicationLog::query()->findOrFail($logId);
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
            ? CommunicationLog::query()->with(['subject', 'notifiable'])->find($this->selectedLogId)
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
    private function timelineFor(CommunicationLog $log): array
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
}; ?>

<section class="w-full">
    <x-admin.layout group="preferences" title="Communication Log" description="Delivery history for outbound notifications and customer communications.">
        <div class="mb-4 grid gap-3 md:grid-cols-3 lg:grid-cols-6">
            <flux:select wire:model.live="filterStatus" label="Status">
                <flux:select.option value="">{{ __('All') }}</flux:select.option>
                @foreach($statuses as $key => $meta)
                    <flux:select.option value="{{ $key }}">{{ $meta['label'] }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input wire:model.live.debounce.300ms="filterChannel" label="Channel" placeholder="email, in_app…" />
            <flux:input wire:model.live.debounce.300ms="filterType" label="Type" placeholder="opportunity.confirmed" />
            <flux:input wire:model.live.debounce.300ms="filterRecipient" label="Recipient" />
            <flux:input wire:model.live="filterDateFrom" type="date" label="From" />
            <flux:input wire:model.live="filterDateTo" type="date" label="To" />
        </div>

        <div class="mb-4">
            <button type="button" class="s-btn s-btn-ghost s-btn-sm" wire:click="clearFilters">{{ __('Clear filters') }}</button>
        </div>

        <x-signals.card>
            <div class="overflow-x-auto">
                <table class="s-table w-full">
                    <thead>
                        <tr>
                            <th><button type="button" wire:click="sortBy('created_at')" class="s-table-sort">{{ __('When') }}</button></th>
                            <th><button type="button" wire:click="sortBy('notification_type_key')" class="s-table-sort">{{ __('Type') }}</button></th>
                            <th>{{ __('Recipient') }}</th>
                            <th><button type="button" wire:click="sortBy('channel')" class="s-table-sort">{{ __('Channel') }}</button></th>
                            <th><button type="button" wire:click="sortBy('status')" class="s-table-sort">{{ __('Status') }}</button></th>
                            <th>{{ __('Subject') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($logs as $log)
                            <tr wire:key="comm-log-{{ $log->id }}">
                                <td class="whitespace-nowrap text-[11px]">@localdatetime($log->created_at)</td>
                                <td class="text-[11px]">{{ $log->notification_type_key }}</td>
                                <td class="text-[11px]">
                                    @if($log->notifiable instanceof \App\Models\Member)
                                        {{ $log->notifiable->name }}
                                    @else
                                        {{ $log->recipient_address ?? '—' }}
                                    @endif
                                </td>
                                <td class="text-[11px]">{{ $log->channel }}</td>
                                <td><span class="s-status s-status-neutral">{{ $statuses[$log->status]['label'] ?? $log->status }}</span></td>
                                <td class="text-[11px]">
                                    @if($log->subject)
                                        {{ class_basename($log->subject_type) }} #{{ $log->subject_id }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="text-right">
                                    <button type="button" class="s-btn s-btn-ghost s-btn-xs" wire:click="showDetail({{ $log->id }})">{{ __('View') }}</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-8 text-center text-[12px] text-[var(--text-muted)]">{{ __('No communication log entries match your filters.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $logs->links() }}</div>
        </x-signals.card>
    </x-admin.layout>

    @if($showDetailModal && $selectedLog)
        <flux:modal wire:model="showDetailModal" class="max-w-2xl">
            <flux:heading size="lg">{{ $selectedLog->subject_line ?? $selectedLog->notification_type_key }}</flux:heading>

            <div class="mt-4 space-y-4">
                <div class="flex items-center justify-between gap-3">
                    <span class="s-status s-status-neutral">{{ $statuses[$selectedLog->status]['label'] ?? $selectedLog->status }}</span>
                    <button type="button" class="s-btn s-btn-ghost s-btn-sm" wire:click="togglePin({{ $selectedLog->id }})">
                        {{ $selectedLog->is_pinned ? __('Unpin') : __('Pin') }}
                    </button>
                </div>

                <div>
                    <div class="text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">{{ __('Timeline') }}</div>
                    <ol class="mt-2 space-y-1 border-l border-[var(--border-subtle)] pl-3">
                        @foreach($timeline as $step)
                            <li wire:key="timeline-{{ $step['label'] }}" class="text-[12px]">
                                <span class="font-medium">{{ $step['label'] }}</span>
                                @if($step['at'])
                                    <span class="text-[var(--text-muted)]"> — @localdatetime($step['at'])</span>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </div>

                @if($selectedLog->rendered_body)
                    <div>
                        <div class="text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">{{ __('Rendered body') }}</div>
                        <div class="mt-2 max-h-64 overflow-y-auto rounded border border-[var(--border-subtle)] bg-[var(--surface-muted)] p-3 text-[12px] whitespace-pre-wrap">{{ $selectedLog->rendered_body }}</div>
                    </div>
                @endif

                @if($selectedLog->failure_reason)
                    <flux:callout variant="danger">{{ $selectedLog->failure_reason }}</flux:callout>
                @endif
            </div>

            <div class="mt-6 flex justify-end">
                <flux:button variant="ghost" wire:click="closeDetail">{{ __('Close') }}</flux:button>
            </div>
        </flux:modal>
    @endif
</section>
