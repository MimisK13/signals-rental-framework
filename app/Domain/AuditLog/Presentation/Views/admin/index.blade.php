<section class="w-full">
    <x-admin.layout group="system" title="Action Log" description="Audit trail of system actions and changes.">
        <x-slot:actions>
            <flux:button variant="ghost" wire:click="exportCsv">Export CSV</flux:button>
        </x-slot:actions>

        <x-action-message on="export-started">Export started. The CSV will be available shortly.</x-action-message>

        {{-- Filters --}}
        <div class="s-card p-4 mb-6">
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                <flux:select wire:model.live="filterAction" label="Action" placeholder="All actions">
                    <flux:select.option value="">All actions</flux:select.option>
                    @foreach ($actions as $action)
                        <flux:select.option value="{{ $action }}">{{ ucfirst($action) }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model.live="filterEntityType" label="Entity Type" placeholder="All types">
                    <flux:select.option value="">All types</flux:select.option>
                    @foreach ($entityTypes as $type)
                        <flux:select.option value="{{ $type }}">{{ class_basename($type) }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model.live.debounce.300ms="filterUser" label="User" placeholder="Search by name..." />

                <flux:input wire:model.live="filterDateFrom" label="From" type="date" />
                <flux:input wire:model.live="filterDateTo" label="To" type="date" />
            </div>

            @if ($filterAction || $filterEntityType || $filterUser || $filterDateFrom || $filterDateTo)
                <div class="mt-3">
                    <flux:button wire:click="clearFilters" variant="ghost" size="sm">Clear filters</flux:button>
                </div>
            @endif
        </div>

        {{-- Table --}}
        @if ($logs->isEmpty())
            <div class="s-card p-8 text-center text-zinc-500 dark:text-zinc-400">
                <p>No action log entries found.</p>
            </div>
        @else
            <div class="s-card overflow-hidden">
                <table class="s-table">
                    <thead>
                        <tr>
                            <th class="w-10"></th>
                            <th>Action</th>
                            <th>Entity</th>
                            <th>User</th>
                            <th>IP Address</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($logs as $log)
                            <tr wire:key="log-{{ $log->id }}">
                                <td class="text-center">
                                    <button
                                        type="button"
                                        wire:click="toggleRow({{ $log->id }})"
                                        aria-label="Toggle audit details"
                                        aria-expanded="{{ $expandedRow === $log->id ? 'true' : 'false' }}"
                                        class="s-btn-ghost s-btn-xs"
                                    >
                                        <flux:icon.chevron-right class="size-4 text-zinc-400 transition-transform {{ $expandedRow === $log->id ? 'rotate-90' : '' }}" />
                                    </button>
                                </td>
                                <td>
                                    <span class="s-badge">{{ ucfirst($log->action) }}</span>
                                </td>
                                <td class="text-xs">
                                    {{ class_basename($log->auditable_type) }} #{{ $log->auditable_id }}
                                </td>
                                <td class="text-xs">{{ $log->user?->name ?? 'System' }}</td>
                                <td class="text-xs font-mono">{{ $log->ip_address }}</td>
                                <td class="text-xs">{{ $log->created_at->format('d M Y H:i:s') }}</td>
                            </tr>
                            @if ($expandedRow === $log->id)
                                <tr wire:key="detail-{{ $log->id }}">
                                    <td colspan="6" class="bg-zinc-50 dark:bg-zinc-800/50 p-4">
                                        <div class="grid grid-cols-2 gap-4 text-xs">
                                            @if ($log->old_values)
                                                <div>
                                                    <h4 class="font-semibold text-zinc-700 dark:text-zinc-300 mb-2">Previous Values</h4>
                                                    <dl class="space-y-1">
                                                        @foreach ($log->old_values as $key => $value)
                                                            <div class="flex gap-2">
                                                                <dt class="text-zinc-500 font-medium">{{ $key }}:</dt>
                                                                <dd class="text-zinc-900 dark:text-zinc-100">{{ is_array($value) ? implode(', ', $value) : $value }}</dd>
                                                            </div>
                                                        @endforeach
                                                    </dl>
                                                </div>
                                            @endif
                                            @if ($log->new_values)
                                                <div>
                                                    <h4 class="font-semibold text-zinc-700 dark:text-zinc-300 mb-2">New Values</h4>
                                                    <dl class="space-y-1">
                                                        @foreach ($log->new_values as $key => $value)
                                                            <div class="flex gap-2">
                                                                <dt class="text-zinc-500 font-medium">{{ $key }}:</dt>
                                                                <dd class="text-zinc-900 dark:text-zinc-100">{{ is_array($value) ? implode(', ', $value) : $value }}</dd>
                                                            </div>
                                                        @endforeach
                                                    </dl>
                                                </div>
                                            @endif
                                        </div>
                                        @if ($log->user_agent)
                                            <p class="text-xs text-zinc-400 mt-3 truncate">{{ $log->user_agent }}</p>
                                        @endif
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $logs->links() }}
            </div>
        @endif
    </x-admin.layout>
</section>
