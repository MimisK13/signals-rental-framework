<?php

use App\Enums\MeteringChannel;
use App\Jobs\ExportApiRequestLog;
use App\Models\InboundWebhookLog;
use App\Models\RequestLog;
use App\Models\WebhookLog;
use App\Support\Api\MeteringStatusClass;
use App\Support\DateWindow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

new #[Layout('components.layouts.app')] #[Title('Request Logs')] class extends Component
{
    use WithPagination;

    #[Url]
    public string $channel = 'api';

    #[Url]
    public string $filterDatePreset = '7d';

    #[Url]
    public string $filterDateFrom = '';

    #[Url]
    public string $filterDateTo = '';

    #[Url]
    public string $filterStatusClass = '';

    #[Url]
    public string $filterToken = '';

    #[Url]
    public string $filterMethod = '';

    public ?int $expandedRow = null;

    public function mount(): void
    {
        Gate::authorize('api.logs.view');
    }

    public function updatedChannel(): void
    {
        $this->filterMethod = '';
        $this->filterToken = '';
        $this->expandedRow = null;
        $this->resetPage();
    }

    public function updatedFilterDatePreset(): void
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

    public function updatedFilterStatusClass(): void
    {
        $this->resetPage();
    }

    public function updatedFilterToken(): void
    {
        $this->resetPage();
    }

    public function updatedFilterMethod(): void
    {
        $this->resetPage();
    }

    public function setChannel(string $channel): void
    {
        $this->channel = $channel;
        $this->updatedChannel();
    }

    public function setDatePreset(string $preset): void
    {
        $this->filterDatePreset = $preset;

        if ($preset !== 'custom') {
            $this->filterDateFrom = '';
            $this->filterDateTo = '';
        }

        $this->resetPage();
    }

    public function toggleRow(int $id): void
    {
        $this->expandedRow = $this->expandedRow === $id ? null : $id;
    }

    public function clearFilters(): void
    {
        $this->filterDatePreset = '7d';
        $this->filterDateFrom = '';
        $this->filterDateTo = '';
        $this->filterStatusClass = '';
        $this->filterToken = '';
        $this->filterMethod = '';
        $this->expandedRow = null;
        $this->resetPage();
    }

    public function exportCsv(): StreamedResponse
    {
        $job = new ExportApiRequestLog((int) auth()->id(), $this->channel, $this->exportFilters());
        $filename = 'api-logs-'.$this->channel.'-'.now()->format('Ymd-His').'.csv';

        $this->dispatch('toast', type: 'success', message: 'Export ready. Downloading…');

        return response()->streamDownload(function () use ($job): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            $job->writeCsv($handle);
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function statusBadgeClass(?int $statusCode): string
    {
        return match (MeteringStatusClass::fromStatusCode($statusCode)) {
            '2xx' => 's-badge-green',
            '4xx' => 's-badge-amber',
            default => 's-badge-red',
        };
    }

    public function formatJson(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '(empty)';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        }

        $decoded = json_decode((string) $value, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        }

        return (string) $value;
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
            'status_class' => $this->filterStatusClass,
            'token_id' => $this->channel === 'api' ? $this->filterToken : null,
            'method' => $this->channel === 'api' ? $this->filterMethod : null,
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

    /**
     * @param  Builder<RequestLog>|Builder<WebhookLog>|Builder<InboundWebhookLog>  $query
     */
    private function applyStatusClassFilter(Builder $query, string $column): void
    {
        if ($this->filterStatusClass === '') {
            return;
        }

        match ($this->filterStatusClass) {
            '2xx' => $query->whereBetween($column, [200, 399]),
            '4xx' => $query->whereBetween($column, [400, 499]),
            '5xx' => $query->where(function (Builder $inner) use ($column) {
                $inner->where($column, '>=', 500)
                    ->orWhere($column, 0)
                    ->orWhereNull($column);
            }),
            default => null,
        };
    }

    public function with(): array
    {
        $window = $this->dateWindow();

        if ($this->channel === 'webhook_sent') {
            $query = WebhookLog::query()
                ->whereBetween('created_at', [$window->from, $window->to])
                ->latest('created_at');

            $this->applyStatusClassFilter($query, 'response_code');

            return [
                'logs' => $query->paginate(25),
                'methods' => collect(),
                'tokens' => collect(),
            ];
        }

        if ($this->channel === 'webhook_received') {
            $query = InboundWebhookLog::query()
                ->with('inboundWebhookSource')
                ->whereBetween('created_at', [$window->from, $window->to])
                ->latest('created_at');

            $this->applyStatusClassFilter($query, 'status_code');

            return [
                'logs' => $query->paginate(25),
                'methods' => collect(),
                'tokens' => collect(),
            ];
        }

        $query = RequestLog::query()
            ->with('token')
            ->where('channel', MeteringChannel::Api->value)
            ->whereBetween('created_at', [$window->from, $window->to])
            ->latest('created_at');

        if ($this->filterMethod !== '') {
            $query->where('method', $this->filterMethod);
        }

        if ($this->filterToken !== '') {
            $query->where('personal_access_token_id', (int) $this->filterToken);
        }

        $this->applyStatusClassFilter($query, 'status_code');

        $tokenIds = RequestLog::query()
            ->where('channel', MeteringChannel::Api->value)
            ->whereNotNull('personal_access_token_id')
            ->distinct()
            ->pluck('personal_access_token_id');

        $tokens = RequestLog::query()
            ->with('token')
            ->whereIn('personal_access_token_id', $tokenIds)
            ->get()
            ->pluck('token')
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values();

        return [
            'logs' => $query->paginate(25),
            'methods' => RequestLog::query()
                ->where('channel', MeteringChannel::Api->value)
                ->distinct()
                ->orderBy('method')
                ->pluck('method'),
            'tokens' => $tokens,
        ];
    }
}; ?>

<section class="w-full">
    <x-api.layout :wide="true" title="Request Logs" :description="'Channel-aware API & webhook log explorer'">
        <x-slot:actions>
            <button type="button" class="s-btn s-btn-ghost" wire:click="exportCsv">Export CSV</button>
        </x-slot:actions>

    <div class="flex flex-wrap gap-2">
        <button type="button" wire:click="setChannel('api')" class="s-chip {{ $channel === 'api' ? 'on' : '' }}">API</button>
        <button type="button" wire:click="setChannel('webhook_sent')" class="s-chip {{ $channel === 'webhook_sent' ? 'on' : '' }}">Webhook sent</button>
        <button type="button" wire:click="setChannel('webhook_received')" class="s-chip {{ $channel === 'webhook_received' ? 'on' : '' }}">Webhook received</button>
    </div>

    <x-signals.card>
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
                        <x-signals.field label="From">
                            <x-signals.datepicker
                                wire:key="logs-filter-from-{{ $filterDateFrom }}"
                                :value="$filterDateFrom ?: null"
                                placeholder="Select date"
                                x-on:input="$wire.set('filterDateFrom', $event.detail ?? '')"
                            />
                        </x-signals.field>
                        <x-signals.field label="To">
                            <x-signals.datepicker
                                wire:key="logs-filter-to-{{ $filterDateTo }}"
                                :value="$filterDateTo ?: null"
                                placeholder="Select date"
                                x-on:input="$wire.set('filterDateTo', $event.detail ?? '')"
                            />
                        </x-signals.field>
                    </div>
                @endif
            </div>

            <div class="grid grid-cols-1 md:grid-cols-{{ $channel === 'api' ? '3' : '1' }} gap-4">
                <x-signals.field label="Status class">
                    <select class="s-input" wire:model.live="filterStatusClass">
                        <option value="">All statuses</option>
                        <option value="2xx">2xx</option>
                        <option value="4xx">4xx</option>
                        <option value="5xx">5xx</option>
                    </select>
                </x-signals.field>

                @if ($channel === 'api')
                    <x-signals.field label="Method">
                        <select class="s-input" wire:model.live="filterMethod">
                            <option value="">All methods</option>
                            @foreach ($methods as $method)
                                <option wire:key="method-{{ $method }}" value="{{ $method }}">{{ $method }}</option>
                            @endforeach
                        </select>
                    </x-signals.field>

                    <x-signals.field label="Token">
                        <select class="s-input" wire:model.live="filterToken">
                            <option value="">All tokens</option>
                            @foreach ($tokens as $token)
                                <option wire:key="token-{{ $token->id }}" value="{{ $token->id }}">{{ $token->name }}</option>
                            @endforeach
                        </select>
                    </x-signals.field>
                @endif
            </div>

            @if ($filterStatusClass || $filterMethod || $filterToken || $filterDatePreset === 'custom')
                <div>
                    <button type="button" class="s-btn s-btn-ghost s-btn-sm" wire:click="clearFilters">Clear filters</button>
                </div>
            @endif
        </div>
    </x-signals.card>

    @if ($logs->isEmpty())
        <x-signals.card>
            <p class="text-sm text-[var(--text-muted)] text-center py-8">No log entries found for the current filters.</p>
        </x-signals.card>
    @else
        <div class="s-card overflow-hidden">
            <table class="s-table">
                <thead>
                    <tr>
                        <th class="w-10"></th>
                        @if ($channel === 'api')
                            <th>Method</th>
                            <th>Route</th>
                            <th>Status</th>
                            <th>Price weight</th>
                            <th>Token</th>
                            <th>Duration</th>
                            <th>Created</th>
                        @elseif ($channel === 'webhook_sent')
                            <th>Event</th>
                            <th>Response</th>
                            <th>Attempts</th>
                            <th>Price weight</th>
                            <th>Delivered</th>
                            <th>Created</th>
                        @else
                            <th>Source</th>
                            <th>Event</th>
                            <th>Signature</th>
                            <th>Status</th>
                            <th>Price weight</th>
                            <th>Created</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($logs as $log)
                        <tr wire:key="log-{{ $channel }}-{{ $log->id }}" class="cursor-pointer" wire:click="toggleRow({{ $log->id }})">
                            <td class="text-center">
                                <flux:icon.chevron-right class="size-4 text-zinc-400 transition-transform {{ $expandedRow === $log->id ? 'rotate-90' : '' }}" />
                            </td>

                            @if ($channel === 'api')
                                <td><span class="s-badge s-badge-zinc">{{ $log->method }}</span></td>
                                <td class="text-xs font-mono">{{ $log->route_name }}</td>
                                <td><span class="s-badge {{ $this->statusBadgeClass($log->status_code) }}">{{ $log->status_code }}</span></td>
                                <td class="text-xs">{{ $log->price_weight }}</td>
                                <td class="text-xs">{{ $log->token?->name ?? '—' }}</td>
                                <td class="text-xs font-mono">{{ $log->duration_ms }} ms</td>
                                <td class="text-xs">{{ $log->created_at?->format('d M Y H:i:s') }}</td>
                            @elseif ($channel === 'webhook_sent')
                                <td class="text-xs font-mono">{{ $log->event }}</td>
                                <td><span class="s-badge {{ $this->statusBadgeClass($log->response_code) }}">{{ $log->response_code ?? '—' }}</span></td>
                                <td class="text-xs">{{ $log->attempts }}</td>
                                <td class="text-xs">{{ $log->price_weight }}</td>
                                <td class="text-xs">{{ $log->delivered_at?->format('d M Y H:i:s') ?? '—' }}</td>
                                <td class="text-xs">{{ $log->created_at?->format('d M Y H:i:s') }}</td>
                            @else
                                <td class="text-xs">{{ $log->inboundWebhookSource?->name ?? '—' }}</td>
                                <td class="text-xs font-mono">{{ $log->event }}</td>
                                <td>
                                    <span class="s-badge {{ $log->signature_valid ? 's-badge-green' : 's-badge-red' }}">
                                        {{ $log->signature_valid ? 'Valid' : 'Invalid' }}
                                    </span>
                                </td>
                                <td><span class="s-badge {{ $this->statusBadgeClass($log->status_code) }}">{{ $log->status_code }}</span></td>
                                <td class="text-xs">{{ $log->price_weight }}</td>
                                <td class="text-xs">{{ $log->created_at?->format('d M Y H:i:s') }}</td>
                            @endif
                        </tr>

                        @if ($expandedRow === $log->id)
                            <tr wire:key="detail-{{ $channel }}-{{ $log->id }}">
                                <td colspan="{{ $channel === 'api' ? 8 : ($channel === 'webhook_sent' ? 7 : 7) }}" class="bg-zinc-50 dark:bg-zinc-800/50 p-4">
                                    @if ($channel === 'api')
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs mb-4">
                                            <dl class="space-y-1">
                                                <div class="flex gap-2"><dt class="text-zinc-500 font-medium">URI:</dt><dd class="font-mono break-all">{{ $log->uri }}</dd></div>
                                                <div class="flex gap-2"><dt class="text-zinc-500 font-medium">Ability:</dt><dd class="font-mono">{{ $log->ability ?? '—' }}</dd></div>
                                                <div class="flex gap-2"><dt class="text-zinc-500 font-medium">Request bytes:</dt><dd>{{ $log->request_bytes }}</dd></div>
                                                <div class="flex gap-2"><dt class="text-zinc-500 font-medium">Response bytes:</dt><dd>{{ $log->response_bytes }}</dd></div>
                                            </dl>
                                            <dl class="space-y-1">
                                                <div class="flex gap-2"><dt class="text-zinc-500 font-medium">IP:</dt><dd class="font-mono">{{ $log->ip_address ?? '—' }}</dd></div>
                                                <div class="flex gap-2"><dt class="text-zinc-500 font-medium">User agent:</dt><dd class="break-all">{{ $log->user_agent ?? '—' }}</dd></div>
                                            </dl>
                                        </div>

                                        @if ($log->request_body)
                                            <div class="mb-4">
                                                <div class="s-section-label mb-2">Request body</div>
                                                <x-signals.json-viewer :content="$this->formatJson($log->request_body)" />
                                            </div>
                                        @endif

                                        @if ($log->response_body)
                                            <div>
                                                <div class="s-section-label mb-2">Response body</div>
                                                <x-signals.json-viewer :content="$this->formatJson($log->response_body)" />
                                            </div>
                                        @endif
                                    @elseif ($channel === 'webhook_sent')
                                        @if ($log->payload)
                                            <div class="mb-4">
                                                <div class="s-section-label mb-2">Payload</div>
                                                <x-signals.json-viewer :content="$this->formatJson($log->payload)" />
                                            </div>
                                        @endif

                                        @if ($log->response_body)
                                            <div>
                                                <div class="s-section-label mb-2">Response body</div>
                                                <x-signals.json-viewer :content="$this->formatJson($log->response_body)" />
                                            </div>
                                        @endif
                                    @else
                                        @if ($log->headers)
                                            <div class="mb-4">
                                                <div class="s-section-label mb-2">Headers</div>
                                                <x-signals.json-viewer :content="$this->formatJson($log->headers)" />
                                            </div>
                                        @endif

                                        @if ($log->payload)
                                            <div>
                                                <div class="s-section-label mb-2">Payload</div>
                                                <x-signals.json-viewer :content="$this->formatJson($log->payload)" />
                                            </div>
                                        @endif
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
    </x-api.layout>
</section>
