<?php

use App\Actions\Api\ExecuteExplorerRequest;
use App\Services\Api\ApiEndpointCatalogue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('API Explorer')] class extends Component
{
    public string $selectedApi = 'signals';

    public string $selectedResource = '';

    public string $selectedRouteName = '';

    /** @var list<array{key: string, value: string}> */
    public array $queryParams = [];

    /** @var array<string, string> */
    public array $pathParams = [];

    /** @var list<array{key: string, value: string}> */
    public array $customHeaders = [];

    public ?int $selectedTokenId = null;

    public string $rawToken = '';

    /** @var array<string, mixed>|null */
    public ?array $response = null;

    public function mount(): void
    {
        Gate::authorize('api.explorer.use');

        $grouped = collect(app(ApiEndpointCatalogue::class)->all())
            ->groupBy(static fn (array $endpoint): string => (string) ($endpoint['resource'] ?? 'other'))
            ->sortKeys();

        if ($grouped->isEmpty()) {
            return;
        }

        $this->selectedResource = (string) $grouped->keys()->first();
        $firstEndpoint = $grouped->get($this->selectedResource)?->first();
        $this->selectedRouteName = (string) ($firstEndpoint['route_name'] ?? '');
        $this->pathParams = $this->pathParamsForUri((string) ($firstEndpoint['uri'] ?? ''));

        $firstToken = auth()->user()->tokens()->orderByDesc('created_at')->first();

        if ($firstToken !== null) {
            $this->selectedTokenId = $firstToken->id;
        }
    }

    /**
     * @return Collection<string, list<array<string, mixed>>>
     */
    #[Computed]
    public function groupedEndpoints(): Collection
    {
        return collect(app(ApiEndpointCatalogue::class)->all())
            ->groupBy(static fn (array $endpoint): string => (string) ($endpoint['resource'] ?? 'other'))
            ->sortKeys();
    }

    /**
     * @return list<string>
     */
    #[Computed]
    public function resources(): array
    {
        return $this->groupedEndpoints->keys()->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function operationsForSelectedResource(): array
    {
        if ($this->selectedResource === '') {
            return [];
        }

        $group = $this->groupedEndpoints[$this->selectedResource] ?? null;

        if ($group === null) {
            return [];
        }

        return $group
            ->sortBy(fn (array $operation): string => strtolower($this->operationDisplayLabel($operation)))
            ->values()
            ->all();
    }

    public function operationDisplayLabel(array $operation): string
    {
        $method = strtoupper((string) ($operation['method'] ?? 'GET'));
        $summary = (string) ($operation['summary'] ?? $operation['operation'] ?? $operation['route_name'] ?? '');

        return $method.' — '.$summary;
    }

    /**
     * @return list<string>
     */
    #[Computed]
    public function pathParameterNames(): array
    {
        $uri = (string) ($this->selectedEndpoint['uri'] ?? '');

        if ($uri === '') {
            return [];
        }

        preg_match_all('/\{([^}]+)\}/', $uri, $matches);

        return array_values($matches[1]);
    }

    #[Computed]
    public function hasPathParameters(): bool
    {
        return $this->pathParameterNames !== [];
    }

    /**
     * @return array<string, mixed>|null
     */
    #[Computed]
    public function selectedEndpoint(): ?array
    {
        if ($this->selectedRouteName === '') {
            return null;
        }

        foreach ($this->groupedEndpoints->flatten(1) as $endpoint) {
            if ($endpoint['route_name'] === $this->selectedRouteName) {
                return $endpoint;
            }
        }

        return null;
    }

    #[Computed]
    public function resolvedUrl(): string
    {
        $endpoint = $this->selectedEndpoint;

        if ($endpoint === null) {
            return '/api/v1/…';
        }

        return $this->substitutePathParameters((string) $endpoint['uri']);
    }

    #[Computed]
    public function formattedResolvedUrlHtml(): string
    {
        $escaped = e($this->resolvedUrl);

        return (string) preg_replace(
            '/(\{[^}]+\})/',
            '<span class="s-explorer-url-placeholder">$1</span>',
            $escaped,
        );
    }

    private function substitutePathParameters(string $uri): string
    {
        foreach ($this->pathParameterNames as $name) {
            $value = trim((string) ($this->pathParams[$name] ?? ''));
            $replacement = $value !== '' ? rawurlencode($value) : '{'.$name.'}';

            $uri = str_replace('{'.$name.'}', $replacement, $uri);
        }

        return $uri;
    }

    /**
     * @return array<string, string>
     */
    private function pathParamsForUri(string $uri): array
    {
        preg_match_all('/\{([^}]+)\}/', $uri, $matches);

        $params = [];

        foreach ($matches[1] as $name) {
            $params[$name] = '';
        }

        return $params;
    }

    #[Computed]
    public function resolvedMethod(): string
    {
        return strtoupper((string) ($this->selectedEndpoint['method'] ?? 'GET'));
    }

    /**
     * @return list<\Laravel\Sanctum\PersonalAccessToken>
     */
    #[Computed]
    public function userTokens(): array
    {
        return auth()->user()->tokens()->orderByDesc('created_at')->get()->all();
    }

    public function updatedSelectedResource(): void
    {
        $operations = $this->operationsForSelectedResource;

        $this->selectedRouteName = (string) ($operations[0]['route_name'] ?? '');
        $this->pathParams = $this->pathParamsForUri((string) ($operations[0]['uri'] ?? ''));
        $this->queryParams = [];
        unset($this->selectedEndpoint, $this->operationsForSelectedResource, $this->resolvedUrl, $this->resolvedMethod, $this->pathParameterNames, $this->hasPathParameters);
    }

    public function updatedSelectedRouteName(): void
    {
        $this->pathParams = $this->pathParamsForUri((string) ($this->selectedEndpoint['uri'] ?? ''));
        $this->queryParams = [];
        unset($this->selectedEndpoint, $this->resolvedUrl, $this->resolvedMethod, $this->pathParameterNames, $this->hasPathParameters);
    }

    public function addQueryParam(): void
    {
        $this->queryParams[] = ['key' => '', 'value' => ''];
    }

    public function removeQueryParam(int $index): void
    {
        if (! array_key_exists($index, $this->queryParams)) {
            return;
        }

        unset($this->queryParams[$index]);
        $this->queryParams = array_values($this->queryParams);
    }

    public function addHeader(): void
    {
        $this->customHeaders[] = ['key' => '', 'value' => ''];
    }

    public function removeHeader(int $index): void
    {
        if (! array_key_exists($index, $this->customHeaders)) {
            return;
        }

        unset($this->customHeaders[$index]);
        $this->customHeaders = array_values($this->customHeaders);
    }

    public function makeRequest(): void
    {
        $endpoint = $this->selectedEndpoint;

        if ($endpoint === null) {
            $this->addError('request', 'Select an API operation before sending a request.');

            return;
        }

        foreach ($this->pathParameterNames as $name) {
            if (trim((string) ($this->pathParams[$name] ?? '')) === '') {
                $this->addError('pathParams.'.$name, 'Provide a value for {'.$name.'}.');

                return;
            }
        }

        $bearerToken = trim($this->rawToken);
        $ephemeralToken = null;

        if ($bearerToken === '') {
            if ($this->selectedTokenId === null) {
                $this->addError('auth', 'Select a token or paste a bearer token.');

                return;
            }

            $token = auth()->user()->tokens()->find($this->selectedTokenId);

            if ($token === null) {
                $this->addError('auth', 'Selected token was not found.');

                return;
            }

            $ephemeralToken = auth()->user()->createToken('api-explorer', $token->abilities ?? []);
            $bearerToken = $ephemeralToken->plainTextToken;
        }

        try {
            $this->response = app(ExecuteExplorerRequest::class)(
                method: (string) $endpoint['method'],
                path: $this->resolvedUrl,
                query: $this->pairsFromRows($this->queryParams),
                headers: $this->pairsFromRows($this->customHeaders),
                bearerToken: $bearerToken,
            );
        } finally {
            if ($ephemeralToken !== null) {
                $ephemeralToken->accessToken->delete();
            }
        }
    }

    public function statusBadgeClass(?string $statusClass): string
    {
        return match ($statusClass) {
            '2xx' => 's-badge-green',
            '4xx' => 's-badge-amber',
            default => 's-badge-red',
        };
    }

    public function methodBadgeClass(string $method): string
    {
        return match (strtoupper($method)) {
            'GET' => 's-badge-blue',
            'POST' => 's-badge-green',
            'PUT', 'PATCH' => 's-badge-amber',
            'DELETE' => 's-badge-red',
            default => 's-badge-zinc',
        };
    }

    /**
     * @param  list<array{key: string, value: string}>  $rows
     * @return array<string, string>
     */
    private function pairsFromRows(array $rows): array
    {
        $pairs = [];

        foreach ($rows as $row) {
            $key = trim((string) ($row['key'] ?? ''));

            if ($key === '') {
                continue;
            }

            $pairs[$key] = (string) ($row['value'] ?? '');
        }

        return $pairs;
    }
}; ?>

<section class="w-full">
    <x-api.layout :wide="true" title="API Explorer" description="Interactive Request Builder">

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 items-start">
        <x-dense::card title="Request" class="s-explorer-panel-card">
            <x-slot:headerActions>
                <div class="s-explorer-panel-header-actions">
                    <button
                        type="button"
                        class="s-btn s-btn-primary"
                        wire:click="makeRequest"
                        wire:loading.attr="disabled"
                        wire:target="makeRequest"
                    >
                        <span wire:loading.remove wire:target="makeRequest">Make request</span>
                        <span wire:loading wire:target="makeRequest">Sending…</span>
                    </button>
                </div>
            </x-slot:headerActions>

            <div class="space-y-6">
                <div>
                    <div class="s-section-label mb-3">API operation</div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <x-dense::field label="API">
                            <select class="s-input" wire:model.live="selectedApi">
                                <option value="signals">Signals API</option>
                            </select>
                        </x-dense::field

                        <x-dense::field label="Endpoint">
                            <select class="s-input" wire:model.live="selectedResource">
                                @foreach ($this->resources as $resource)
                                    <option wire:key="resource-{{ $resource }}" value="{{ $resource }}">
                                        {{ str($resource)->headline() }}
                                    </option>
                                @endforeach
                            </select>
                        </x-dense::field

                        <x-dense::field label="Operation">
                            <select class="s-input" wire:model.live="selectedRouteName">
                                @foreach ($this->operationsForSelectedResource as $operation)
                                    <option wire:key="operation-{{ $operation['route_name'] }}" value="{{ $operation['route_name'] }}">
                                        {{ $this->operationDisplayLabel($operation) }}
                                    </option>
                                @endforeach
                            </select>
                        </x-dense::field
                    </div>
                </div>

                <div>
                    <div class="s-section-label mb-2">Resolved URL</div>
                    <div class="s-explorer-url">
                        <span class="s-badge {{ $this->methodBadgeClass($this->resolvedMethod) }}">{{ $this->resolvedMethod }}</span>
                        <span class="s-explorer-url-path">{!! $this->formattedResolvedUrlHtml !!}</span>
                    </div>
                </div>

                @if ($this->hasPathParameters)
                    <div>
                        <div class="s-section-label mb-3">Path parameters</div>
                        <p class="text-xs text-[var(--text-muted)] mb-3">Replace route placeholders such as <code class="font-mono">{member}</code> with the record ID or slug for this request.</p>
                        <div class="space-y-2">
                            @foreach ($this->pathParameterNames as $name)
                                <div wire:key="path-param-{{ $name }}" class="grid grid-cols-[120px_1fr] gap-2 items-start">
                                    <label class="font-mono text-xs text-[var(--text-secondary)] pt-2.5" for="path-param-{{ $name }}">{{ '{'.$name.'}' }}</label>
                                    <div>
                                        <input
                                            id="path-param-{{ $name }}"
                                            type="text"
                                            class="s-input font-mono text-xs"
                                            placeholder="Value for {{ $name }}"
                                            wire:model.live="pathParams.{{ $name }}"
                                        >
                                        @error('pathParams.'.$name)
                                            <p class="text-xs text-[var(--danger)] mt-1">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div>
                    <div class="flex items-center justify-between mb-3">
                        <div class="s-section-label">Query parameters</div>
                        <button type="button" class="s-btn s-btn-ghost s-btn-sm" wire:click="addQueryParam">Add parameter</button>
                    </div>

                    <div class="space-y-2">
                        @forelse ($queryParams as $index => $param)
                            <div wire:key="query-param-{{ $index }}" class="grid grid-cols-[1fr_1fr_auto] gap-2">
                                <input type="text" class="s-input font-mono text-xs" placeholder="Key" wire:model.live="queryParams.{{ $index }}.key">
                                <input type="text" class="s-input font-mono text-xs" placeholder="Value" wire:model.live="queryParams.{{ $index }}.value">
                                <button type="button" class="s-btn s-btn-ghost s-btn-sm" wire:click="removeQueryParam({{ $index }})">Remove</button>
                            </div>
                        @empty
                            <p class="text-sm text-[var(--text-muted)]">No query parameters added.</p>
                        @endforelse
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-3">
                        <div class="s-section-label">Headers</div>
                        <button type="button" class="s-btn s-btn-ghost s-btn-sm" wire:click="addHeader">Add header</button>
                    </div>

                    <div class="space-y-2 mb-4">
                        @forelse ($customHeaders as $index => $header)
                            <div wire:key="header-{{ $index }}" class="grid grid-cols-[1fr_1fr_auto] gap-2">
                                <input type="text" class="s-input font-mono text-xs" placeholder="Header" wire:model.live="customHeaders.{{ $index }}.key">
                                <input type="text" class="s-input font-mono text-xs" placeholder="Value" wire:model.live="customHeaders.{{ $index }}.value">
                                <button type="button" class="s-btn s-btn-ghost s-btn-sm" wire:click="removeHeader({{ $index }})">Remove</button>
                            </div>
                        @empty
                            <p class="text-sm text-[var(--text-muted)]">No custom headers added.</p>
                        @endforelse
                    </div>

                    <x-signals.accordion>
                        <x-signals.accordion-item id="auto-headers" label="Auto-generated headers">
                            <div class="space-y-2 font-mono text-xs text-[var(--text-secondary)]">
                                <div class="flex items-center justify-between gap-4 border border-[var(--card-border)] px-3 py-2">
                                    <span>Accept</span>
                                    <span>application/json</span>
                                </div>
                                <div class="flex items-center justify-between gap-4 border border-[var(--card-border)] px-3 py-2">
                                    <span>Authorization</span>
                                    <span>Bearer {{ \App\Actions\Api\ExecuteExplorerRequest::maskToken($rawToken !== '' ? $rawToken : 'token') }}</span>
                                </div>
                            </div>
                        </x-signals.accordion-item>
                    </x-signals.accordion>
                </div>

                <div>
                    <div class="s-section-label mb-3">Auth</div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <x-dense::field label="Token abilities to emulate">
                            <select class="s-input" wire:model.live="selectedTokenId">
                                <option value="">Select a token…</option>
                                @foreach ($this->userTokens as $token)
                                    <option wire:key="token-{{ $token->id }}" value="{{ $token->id }}">{{ $token->name }}</option>
                                @endforeach
                            </select>
                        </x-dense::field

                        <x-dense::field label="Or paste a raw token" :error="$errors->first('auth')">
                            <input type="password" class="s-input font-mono text-xs" placeholder="Paste bearer token" wire:model.live="rawToken" autocomplete="off">
                        </x-dense::field
                    </div>
                </div>

                @error('request')
                    <div class="s-alert s-alert-danger">{{ $message }}</div>
                @enderror
            </div>
        </x-dense::card>

        <x-dense::card title="Response" class="s-explorer-panel-card">
            <x-slot:headerActions>
                <div class="s-explorer-panel-header-actions">
                    @if ($response)
                        <span class="s-badge {{ $this->statusBadgeClass($response['status_class'] ?? null) }}">
                            {{ ($response['status'] ?? 0) > 0 ? $response['status'] : 'ERR' }}
                        </span>
                        <span class="text-xs text-[var(--text-muted)]">{{ $response['duration_ms'] ?? 0 }} ms</span>
                    @else
                        <span class="s-btn s-btn-primary invisible pointer-events-none" aria-hidden="true">Make request</span>
                    @endif
                </div>
            </x-slot:headerActions>

            <div class="space-y-4 min-h-[320px]">
                <div wire:loading wire:target="makeRequest" class="flex items-center gap-2 text-sm text-[var(--text-muted)]">
                    <x-signals.spinner size="sm" />
                    Sending request…
                </div>

                @if ($response === null)
                    <p wire:loading.remove wire:target="makeRequest" class="text-sm text-[var(--text-muted)]">Send a request to inspect the response here.</p>
                @else
                    @if (! empty($response['error']))
                        <div class="s-alert s-alert-danger">{{ $response['error'] }}</div>
                    @endif

                    @if ($response['headers'] !== [])
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <div class="s-section-label">Response headers</div>
                                <span class="text-[10px] text-[var(--text-muted)]">{{ count($response['headers']) }} header(s)</span>
                            </div>
                            <div class="s-response-headers-panel">
                                <dl class="s-response-headers">
                                    @foreach ($response['headers'] as $name => $values)
                                        <div wire:key="response-header-{{ $name }}" class="s-response-header-row">
                                            <dt class="s-response-header-name">{{ $name }}</dt>
                                            <dd class="s-response-header-value">{{ implode(', ', $values) }}</dd>
                                        </div>
                                    @endforeach
                                </dl>
                            </div>
                        </div>
                    @endif

                    <div>
                        <div class="s-section-label mb-2">Body</div>
                        <x-signals.json-viewer copy compact :content="$response['body'] !== '' ? $response['body'] : '(empty body)'" />
                    </div>
                @endif
            </div>
        </x-dense::card>
    </div>
    </x-api.layout>
</section>
