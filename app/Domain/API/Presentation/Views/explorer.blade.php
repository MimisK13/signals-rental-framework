<section class="w-full min-w-0">
    <x-api::layout :wide="true" title="API Explorer" description="Interactive Request Builder">

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
                        </x-dense::field>

                        <x-dense::field label="Endpoint">
                            <select class="s-input" wire:model.live="selectedResource">
                                @foreach ($this->resources as $resource)
                                    <option wire:key="resource-{{ $resource }}" value="{{ $resource }}">
                                        {{ str($resource)->headline() }}
                                    </option>
                                @endforeach
                            </select>
                        </x-dense::field>

                        <x-dense::field label="Operation">
                            <select class="s-input" wire:model.live="selectedRouteName">
                                @foreach ($this->operationsForSelectedResource as $operation)
                                    <option wire:key="operation-{{ $operation['route_name'] }}" value="{{ $operation['route_name'] }}">
                                        {{ $this->operationDisplayLabel($operation) }}
                                    </option>
                                @endforeach
                            </select>
                        </x-dense::field>
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
                                    <span>Bearer {{ \App\Domain\API\Actions\ExecuteExplorerRequest::maskToken($rawToken !== '' ? $rawToken : 'token') }}</span>
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
                        </x-dense::field>

                        <x-dense::field label="Or paste a raw token" :error="$errors->first('auth')">
                            <input type="password" class="s-input font-mono text-xs" placeholder="Paste bearer token" wire:model.live="rawToken" autocomplete="off">
                        </x-dense::field>
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
    </x-api::layout>
</section>
