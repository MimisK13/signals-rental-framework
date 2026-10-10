<?php

use App\Enums\MeteringChannel;
use App\Jobs\ExportApiUsage;
use App\Queries\Api\UsageQuery;
use App\Support\DateWindow;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

new #[Layout('components.layouts.app')] #[Title('API Usage')] class extends Component
{
    #[Url]
    public string $filterDatePreset = '7d';

    #[Url]
    public string $filterDateFrom = '';

    #[Url]
    public string $filterDateTo = '';

    #[Url]
    public string $channel = '';

    public function mount(): void
    {
        Gate::authorize('api.usage.view');
    }

    public function updatedFilterDatePreset(): void
    {
        if ($this->filterDatePreset !== 'custom') {
            $this->filterDateFrom = '';
            $this->filterDateTo = '';
        }
    }

    public function setDatePreset(string $preset): void
    {
        $this->filterDatePreset = $preset;

        if ($preset !== 'custom') {
            $this->filterDateFrom = '';
            $this->filterDateTo = '';
        }
    }

    public function setChannel(string $channel): void
    {
        $this->channel = $channel;
    }

    public function exportCsv(): StreamedResponse
    {
        $job = new ExportApiUsage((int) auth()->id(), $this->exportFilters());
        $usageQuery = app(UsageQuery::class);
        $filename = 'api-usage-'.now()->format('Ymd-His').'.csv';

        $this->dispatch('toast', type: 'success', message: 'Export ready. Downloading…');

        return response()->streamDownload(function () use ($job, $usageQuery): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            $job->writeCsv($handle, $usageQuery);
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * @return array<string, mixed>
     */
    private function exportFilters(): array
    {
        return array_filter([
            'window' => $this->filterDatePreset,
            'from' => $this->filterDateFrom,
            'to' => $this->filterDateTo,
            'channel' => $this->channel !== '' ? $this->channel : null,
            'dimension' => 'endpoint',
        ], fn ($value) => $value !== '' && $value !== null);
    }

    private function dateWindow(): DateWindow
    {
        if ($this->filterDatePreset === 'custom') {
            if ($this->filterDateFrom === '' || $this->filterDateTo === '') {
                return DateWindow::fromRequest('7d');
            }

            return DateWindow::fromRequest('custom', $this->filterDateFrom, $this->filterDateTo);
        }

        return DateWindow::fromRequest($this->filterDatePreset);
    }

    private function resolvedChannel(): ?MeteringChannel
    {
        if ($this->channel === '') {
            return null;
        }

        return MeteringChannel::from($this->channel);
    }

    /**
     * @param  list<array{key: string, label: string|null, request_count: int, weighted_units: int}>  $rows
     */
    public function statusCount(array $rows, string $statusClass): int
    {
        foreach ($rows as $row) {
            if ($row['key'] === $statusClass) {
                return $row['request_count'];
            }
        }

        return 0;
    }

    /**
     * @param  list<array{key: string, label: string|null, request_count: int, weighted_units: int}>  $rows
     */
    public function statusBarPercent(array $rows, string $statusClass): int
    {
        $total = array_sum(array_column($rows, 'request_count'));

        if ($total === 0) {
            return 0;
        }

        return (int) round(($this->statusCount($rows, $statusClass) / $total) * 100);
    }

    public function with(): array
    {
        $window = $this->dateWindow();
        $channel = $this->resolvedChannel();
        $usageQuery = app(UsageQuery::class);

        $summary = $usageQuery($window, 'endpoint', $channel);
        $dailyRows = collect($usageQuery($window, 'day', $channel)->rows)
            ->sortBy('key')
            ->values()
            ->all();
        $tokenRows = collect($usageQuery($window, 'token', $channel)->rows)
            ->sortByDesc('weighted_units')
            ->values()
            ->all();
        $endpointRows = $usageQuery($window, 'endpoint', $channel)->rows;
        $resourceRows = $usageQuery($window, 'resource', $channel)->rows;
        $statusRows = $usageQuery($window, 'status', $channel)->rows;

        $maxDailyRequests = max(collect($dailyRows)->max('request_count') ?: 0, 1);

        return [
            'totals' => $summary->totals,
            'dailyRows' => $dailyRows,
            'maxDailyRequests' => $maxDailyRequests,
            'tokenRows' => $tokenRows,
            'endpointRows' => $endpointRows,
            'resourceRows' => $resourceRows,
            'statusRows' => $statusRows,
        ];
    }
}; ?>

<section class="w-full">
    <x-api.layout :wide="true" title="API Usage" description="Aggregated request volume and weighted units">
        <x-slot:actions>
            <button type="button" class="s-btn s-btn-ghost" wire:click="exportCsv" wire:loading.attr="disabled" wire:target="exportCsv">
                <span wire:loading.remove wire:target="exportCsv">Export CSV</span>
                <span wire:loading wire:target="exportCsv">Exporting…</span>
            </button>
        </x-slot:actions>

    <div class="flex flex-wrap gap-2">
        <button type="button" wire:click="setChannel('')" class="s-chip {{ $channel === '' ? 'on' : '' }}">All</button>
        <button type="button" wire:click="setChannel('api')" class="s-chip {{ $channel === 'api' ? 'on' : '' }}">API</button>
        <button type="button" wire:click="setChannel('webhook_sent')" class="s-chip {{ $channel === 'webhook_sent' ? 'on' : '' }}">Webhook sent</button>
        <button type="button" wire:click="setChannel('webhook_received')" class="s-chip {{ $channel === 'webhook_received' ? 'on' : '' }}">Webhook received</button>
    </div>

    <x-dense::card>
        <div class="space-y-4">
            <div>
                <div class="s-section-label mb-3">Date range</div>
                <div class="flex flex-wrap gap-2 mb-4">
                    <button type="button" wire:click="setDatePreset('24h')" class="s-chip {{ $filterDatePreset === '24h' ? 'on' : '' }}">24h</button>
                    <button type="button" wire:click="setDatePreset('7d')" class="s-chip {{ $filterDatePreset === '7d' ? 'on' : '' }}">7d</button>
                    <button type="button" wire:click="setDatePreset('30d')" class="s-chip {{ $filterDatePreset === '30d' ? 'on' : '' }}">30d</button>
                    <button type="button" wire:click="setDatePreset('custom')" class="s-chip {{ $filterDatePreset === 'custom' ? 'on' : '' }}">Custom</button>
                </div>

                @if ($filterDatePreset === 'custom')
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <x-dense::field label="From">
                            <x-signals.datepicker
                                wire:key="usage-filter-from-{{ $filterDateFrom }}"
                                :value="$filterDateFrom ?: null"
                                placeholder="Select date"
                                x-on:input="$wire.set('filterDateFrom', $event.detail ?? '')"
                            />
                        </x-dense::field
                        <x-dense::field label="To">
                            <x-signals.datepicker
                                wire:key="usage-filter-to-{{ $filterDateTo }}"
                                :value="$filterDateTo ?: null"
                                placeholder="Select date"
                                x-on:input="$wire.set('filterDateTo', $event.detail ?? '')"
                            />
                        </x-dense::field
                    </div>
                @endif
            </div>
        </div>
    </x-dense::card>

    <x-dense::card class="s-usage-overview">
        <div class="s-usage-overview-grid">
            <div class="s-usage-overview-top">
                <div class="s-usage-metric">
                    <div class="s-usage-metric-label">Total requests</div>
                    <div class="s-usage-metric-value">{{ number_format($totals['request_count']) }}</div>
                </div>

                <div class="s-usage-metric">
                    <div class="s-usage-metric-label">Total weighted units</div>
                    <div class="s-usage-metric-value">{{ number_format($totals['weighted_units']) }}</div>
                </div>

                <div class="s-usage-status-split">
                    <div class="s-usage-status-split-label">Status split</div>
                    <div class="s-usage-status-grid">
                        @foreach (['2xx' => 'green', '4xx' => 'amber', '5xx' => 'red'] as $statusClass => $barColor)
                            @php
                                $count = $this->statusCount($statusRows, $statusClass);
                                $percent = $this->statusBarPercent($statusRows, $statusClass);
                            @endphp
                            <div wire:key="status-{{ $statusClass }}" class="s-usage-status-item">
                                <div class="s-usage-status-item-head">
                                    <span class="s-badge s-badge-{{ $barColor }}">{{ $statusClass }}</span>
                                    <span class="s-usage-status-count">{{ number_format($count) }}</span>
                                </div>
                                <div class="s-bar-track s-usage-status-track">
                                    <div class="s-bar-fill s-bar-fill-{{ $barColor }} h-full" style="width: {{ max($percent, $count > 0 ? 6 : 0) }}%;"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="s-usage-daily">
                <div class="s-usage-daily-label">Daily usage</div>
                @if ($dailyRows === [])
                    <p class="text-sm text-[var(--text-muted)] py-2">No usage recorded for the selected window.</p>
                @else
                    <x-signals.bar-chart class="s-usage-daily-chart">
                        @foreach ($dailyRows as $row)
                            @php
                                $requestPercent = (int) round(($row['request_count'] / $maxDailyRequests) * 100);
                            @endphp
                            <div wire:key="daily-{{ $row['key'] }}" class="s-bar-row s-usage-bar-row">
                                <span class="s-bar-label">{{ $row['key'] }}</span>
                                <div class="s-bar-track s-usage-bar-track">
                                    <div class="s-bar-fill s-bar-fill-blue" style="width: {{ max($requestPercent, $row['request_count'] > 0 ? 8 : 0) }}%;">
                                        @if ($requestPercent >= 18)
                                            <span class="s-bar-value">{{ number_format($row['request_count']) }}</span>
                                        @endif
                                    </div>
                                </div>
                                <span class="s-usage-bar-meta">
                                    {{ number_format($row['request_count']) }} req · {{ number_format($row['weighted_units']) }} units
                                </span>
                            </div>
                        @endforeach
                    </x-signals.bar-chart>
                @endif
            </div>
        </div>
    </x-dense::card>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        <x-dense::card title="By token" class="s-usage-table-card">
            @if ($tokenRows === [])
                <p class="text-sm text-[var(--text-muted)] text-center py-6">No token usage in this window.</p>
            @else
                <x-signals.table-wrap class="s-usage-table-wrap">
                    <table class="s-table s-table-compact">
                        <thead>
                            <tr>
                                <th>Token</th>
                                <th class="text-right">Requests</th>
                                <th class="text-right">Weighted units</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($tokenRows as $row)
                                <tr wire:key="token-{{ $row['key'] }}">
                                    <td>
                                        <div class="font-medium">{{ $row['label'] ?? 'Unscoped' }}</div>
                                        @if ($row['key'] !== 'null')
                                            <div class="text-xs font-mono text-[var(--text-muted)]">#{{ $row['key'] }}</div>
                                        @endif
                                    </td>
                                    <td class="text-right tabular-nums">{{ number_format($row['request_count']) }}</td>
                                    <td class="text-right tabular-nums">{{ number_format($row['weighted_units']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-signals.table-wrap>
            @endif
        </x-dense::card>

        <x-dense::card title="By endpoint" class="s-usage-table-card">
            @if ($endpointRows === [])
                <p class="text-sm text-[var(--text-muted)] text-center py-6">No endpoint usage in this window.</p>
            @else
                <x-signals.table-wrap class="s-usage-table-wrap">
                    <table class="s-table s-table-compact">
                        <thead>
                            <tr>
                                <th>Endpoint</th>
                                <th class="text-right">Requests</th>
                                <th class="text-right">Weighted units</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($endpointRows as $row)
                                <tr wire:key="endpoint-{{ $row['key'] }}">
                                    <td class="font-mono text-xs">{{ $row['key'] }}</td>
                                    <td class="text-right tabular-nums">{{ number_format($row['request_count']) }}</td>
                                    <td class="text-right tabular-nums">{{ number_format($row['weighted_units']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-signals.table-wrap>
            @endif
        </x-dense::card>

        <x-dense::card title="By resource" class="s-usage-table-card xl:col-span-2">
            @if ($resourceRows === [])
                <p class="text-sm text-[var(--text-muted)] text-center py-6">No resource usage in this window.</p>
            @else
                <x-signals.table-wrap class="s-usage-table-wrap">
                    <table class="s-table s-table-compact">
                        <thead>
                            <tr>
                                <th>Resource</th>
                                <th class="text-right">Requests</th>
                                <th class="text-right">Weighted units</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($resourceRows as $row)
                                <tr wire:key="resource-{{ $row['key'] }}">
                                    <td>{{ $row['label'] ?? $row['key'] }}</td>
                                    <td class="text-right tabular-nums">{{ number_format($row['request_count']) }}</td>
                                    <td class="text-right tabular-nums">{{ number_format($row['weighted_units']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-signals.table-wrap>
            @endif
        </x-dense::card>
    </div>
    </x-api.layout>
</section>
