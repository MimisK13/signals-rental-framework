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

        <x-dense::card>
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
        </x-dense::card>
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
