@props([
    'logs',
    'statuses',
    'emptyMessage' => 'No communications recorded for this record yet.',
])

<x-dense::card>
    <div class="overflow-x-auto">
        <table class="s-table w-full">
            <thead>
                <tr>
                    <th>{{ __('When') }}</th>
                    <th>{{ __('Type') }}</th>
                    <th>{{ __('Channel') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th>{{ __('Recipient') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                    <tr wire:key="entity-comm-{{ $log->id }}">
                        <td class="whitespace-nowrap text-[11px]">@localdatetime($log->created_at)</td>
                        <td class="text-[11px]">{{ $log->notification_type_key }}</td>
                        <td class="text-[11px]">{{ $log->channel }}</td>
                        <td><span class="s-status s-status-neutral">{{ $statuses[$log->status]['label'] ?? $log->status }}</span></td>
                        <td class="text-[11px]">
                            @if($log->notifiable instanceof \App\Models\Member)
                                {{ $log->notifiable->name }}
                            @else
                                {{ $log->recipient_address ?? '—' }}
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="py-8 text-center text-[12px] text-[var(--text-muted)]">{{ $emptyMessage }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-dense::card>
