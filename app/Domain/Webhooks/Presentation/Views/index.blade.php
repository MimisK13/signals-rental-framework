<section class="w-full">
    <x-api::layout :wide="true" title="Webhooks" description="Manage outbound subscriptions and inbound webhook sources">
        <x-slot:actions>
            @if ($tab === 'outbound')
                <button type="button" class="s-btn s-btn-primary" wire:click="openCreateModal">Create Webhook</button>
            @else
                <button type="button" class="s-btn s-btn-primary" wire:click="openInboundCreateModal">Create Source</button>
            @endif
        </x-slot:actions>

    <div class="flex flex-wrap gap-2">
        <button type="button" wire:click="setTab('outbound')" class="s-chip {{ $tab === 'outbound' ? 'on' : '' }}">Outbound</button>
        <button type="button" wire:click="setTab('inbound')" class="s-chip {{ $tab === 'inbound' ? 'on' : '' }}">Inbound</button>
    </div>

    @if ($tab === 'outbound')
        {{-- Outbound secret banner --}}
        @if ($showOutboundSecret)
            <div class="rounded-lg border border-green-200 bg-green-50 p-4 dark:border-green-800 dark:bg-green-950">
                <div class="flex items-start gap-3">
                    <flux:icon.check-circle class="w-5 h-5 text-green-600 dark:text-green-400 mt-0.5 shrink-0" />
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-green-800 dark:text-green-200">Webhook created successfully</p>
                        <p class="mt-1 text-xs text-green-700 dark:text-green-300">Copy this secret now. It will not be shown again.</p>
                        <div class="mt-2 flex items-center gap-2">
                            <code class="block flex-1 rounded bg-green-100 px-3 py-2 font-mono text-sm text-green-900 dark:bg-green-900 dark:text-green-100 break-all">{{ $plainOutboundSecret }}</code>
                            <button
                                x-data="{ copied: false }"
                                x-on:click="navigator.clipboard.writeText(@js($plainOutboundSecret)); copied = true; setTimeout(() => copied = false, 2000)"
                                class="s-btn s-btn-sm s-btn-ghost shrink-0"
                                title="Copy to clipboard"
                            >
                                <template x-if="!copied"><flux:icon.clipboard class="w-4 h-4" /></template>
                                <template x-if="copied"><flux:icon.check class="w-4 h-4 text-green-600" /></template>
                            </button>
                        </div>
                        <div class="mt-2">
                            <flux:button variant="ghost" size="sm" wire:click="dismissOutboundSecret">Dismiss</flux:button>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @if ($webhooks->isNotEmpty())
            <div class="s-card overflow-hidden">
                <table class="s-table">
                    <thead>
                        <tr>
                            <th>URL</th>
                            <th>Events</th>
                            <th>Status</th>
                            <th>Failures</th>
                            <th>Owner</th>
                            <th>Created</th>
                            <th class="w-24"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($webhooks as $webhook)
                            <tr wire:key="webhook-{{ $webhook->id }}">
                                <td class="font-medium max-w-xs truncate" title="{{ $webhook->url }}">{{ $webhook->url }}</td>
                                <td>
                                    @php $webhookEvents = $webhook->events ?? []; @endphp
                                    <div x-data="{ open: false }" class="text-sm">
                                        <button type="button" class="flex items-center gap-1 text-zinc-600 hover:text-zinc-900 dark:text-zinc-300 dark:hover:text-zinc-100" x-on:click="open = !open">
                                            <flux:icon.chevron-right class="w-3.5 h-3.5 transition-transform" x-bind:class="open && 'rotate-90'" />
                                            {{ count($webhookEvents) }} {{ \Illuminate\Support\Str::plural('event', count($webhookEvents)) }}
                                        </button>
                                        <div x-show="open" class="mt-1.5 space-y-0.5">
                                            @foreach ($this->groupedEvents($webhookEvents) as $groupLabel => $actions)
                                                <div class="text-xs text-zinc-500">
                                                    <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ $groupLabel }}:</span>
                                                    {{ implode(', ', $actions) }}
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    @if ($webhook->is_active)
                                        <span class="s-status s-status-green">Active</span>
                                    @else
                                        <span class="s-status s-status-red">Disabled</span>
                                    @endif
                                </td>
                                <td class="text-sm text-zinc-500">{{ $webhook->consecutive_failures }}</td>
                                <td class="text-sm text-zinc-500">{{ $webhook->user?->name ?? 'Unknown' }}</td>
                                <td class="text-sm text-zinc-500">{{ $webhook->created_at->diffForHumans() }}</td>
                                <td>
                                    @include('webhooks::partials.outbound-actions', ['webhook' => $webhook])
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <x-dense::card>
                <div class="py-8 text-center">
                    <flux:icon.signal class="mx-auto h-10 w-10 text-zinc-400" />
                    <p class="mt-3 text-sm font-medium text-zinc-900 dark:text-zinc-100">No webhooks</p>
                    <p class="mt-1 text-sm text-zinc-500">Create a webhook to receive event notifications at an external URL.</p>
                </div>
            </x-dense::card>
        @endif

        <x-action-message on="webhook-created">Webhook created successfully.</x-action-message>
        <x-action-message on="webhook-updated">Webhook updated successfully.</x-action-message>
        <x-action-message on="webhook-deleted">Webhook deleted.</x-action-message>
        <x-action-message on="webhook-reenabled">Webhook re-enabled.</x-action-message>
    @else
        {{-- Inbound secret banner --}}
        @if ($showInboundSecret)
            <div class="rounded-lg border border-green-200 bg-green-50 p-4 dark:border-green-800 dark:bg-green-950">
                <div class="flex items-start gap-3">
                    <flux:icon.check-circle class="w-5 h-5 text-green-600 dark:text-green-400 mt-0.5 shrink-0" />
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-green-800 dark:text-green-200">Inbound webhook secret</p>
                        <p class="mt-1 text-xs text-green-700 dark:text-green-300">Copy this secret now. It will not be shown again.</p>
                        <div class="mt-2 flex items-center gap-2">
                            <code class="block flex-1 rounded bg-green-100 px-3 py-2 font-mono text-sm text-green-900 dark:bg-green-900 dark:text-green-100 break-all">{{ $plainInboundSecret }}</code>
                            <button
                                x-data="{ copied: false }"
                                x-on:click="navigator.clipboard.writeText(@js($plainInboundSecret)); copied = true; setTimeout(() => copied = false, 2000)"
                                class="s-btn s-btn-sm s-btn-ghost shrink-0"
                                title="Copy to clipboard"
                            >
                                <template x-if="!copied"><flux:icon.clipboard class="w-4 h-4" /></template>
                                <template x-if="copied"><flux:icon.check class="w-4 h-4 text-green-600" /></template>
                            </button>
                        </div>
                        <div class="mt-2">
                            <flux:button variant="ghost" size="sm" wire:click="dismissInboundSecret">Dismiss</flux:button>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @if ($inboundSources->isNotEmpty())
            <div class="s-card overflow-hidden">
                <table class="s-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Receive URL</th>
                            <th>Signature</th>
                            <th>Price weight</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th class="w-32"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($inboundSources as $source)
                            <tr wire:key="inbound-source-{{ $source->id }}">
                                <td>
                                    <div class="font-medium">{{ $source->name }}</div>
                                    <div class="text-xs text-zinc-500 font-mono">{{ $source->slug }}</div>
                                </td>
                                <td>
                                    @php $receiveUrl = $this->receiveUrl($source->slug); @endphp
                                    <div class="flex items-center gap-1 max-w-xs">
                                        <code class="text-xs font-mono truncate" title="{{ $receiveUrl }}">{{ $receiveUrl }}</code>
                                        <button
                                            x-data="{ copied: false }"
                                            x-on:click="navigator.clipboard.writeText(@js($receiveUrl)); copied = true; setTimeout(() => copied = false, 2000)"
                                            class="s-btn s-btn-ghost s-btn-sm shrink-0"
                                            title="Copy receive URL"
                                        >
                                            <template x-if="!copied"><flux:icon.clipboard class="w-3.5 h-3.5" /></template>
                                            <template x-if="copied"><flux:icon.check class="w-3.5 h-3.5 text-green-600" /></template>
                                        </button>
                                    </div>
                                </td>
                                <td>
                                    <div class="text-sm">{{ strtoupper($source->signature_algo) }}</div>
                                    <div class="text-xs text-zinc-500 font-mono">{{ $source->signature_header }}</div>
                                </td>
                                <td class="text-sm text-zinc-500">{{ $source->price_weight }}</td>
                                <td>
                                    @if ($source->is_active)
                                        <span class="s-status s-status-green">Active</span>
                                    @else
                                        <span class="s-status s-status-red">Inactive</span>
                                    @endif
                                </td>
                                <td class="text-sm text-zinc-500">{{ $source->created_at->diffForHumans() }}</td>
                                <td>
                                    @include('webhooks::partials.inbound-actions', ['source' => $source])
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <x-dense::card>
                <div class="py-8 text-center">
                    <flux:icon.arrow-down-tray class="mx-auto h-10 w-10 text-zinc-400" />
                    <p class="mt-3 text-sm font-medium text-zinc-900 dark:text-zinc-100">No inbound sources</p>
                    <p class="mt-1 text-sm text-zinc-500">Create a source to receive signed webhooks from external systems.</p>
                </div>
            </x-dense::card>
        @endif

        <x-action-message on="inbound-source-created">Inbound source created successfully.</x-action-message>
        <x-action-message on="inbound-source-updated">Inbound source updated successfully.</x-action-message>
        <x-action-message on="inbound-source-deleted">Inbound source deleted.</x-action-message>
        <x-action-message on="inbound-secret-rotated">Secret rotated successfully.</x-action-message>
        <x-action-message on="inbound-source-toggled">Source status updated.</x-action-message>
    @endif

    {{-- Outbound Create Modal --}}
    <flux:modal wire:model="showCreateModal">
        <div class="space-y-6">
            <flux:heading size="lg">Create Webhook</flux:heading>

            <form wire:submit="createWebhook" class="space-y-4">
                <flux:input wire:model="createUrl" label="Endpoint URL" placeholder="https://example.com/webhook" required />

                <div>
                    @include('webhooks::partials.event-picker', ['model' => 'createEvents', 'groups' => $eventGroups])
                    @error('createEvents') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="flex justify-end gap-3">
                    <flux:button variant="ghost" wire:click="$set('showCreateModal', false)">Cancel</flux:button>
                    <flux:button variant="primary" type="submit">Create Webhook</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    {{-- Outbound Edit Modal --}}
    <flux:modal wire:model="showEditModal">
        <div class="space-y-6">
            <flux:heading size="lg">Edit Webhook</flux:heading>

            <form wire:submit="updateWebhook" class="space-y-4">
                <flux:input wire:model="editUrl" label="Endpoint URL" placeholder="https://example.com/webhook" required />

                <div>
                    @include('webhooks::partials.event-picker', ['model' => 'editEvents', 'groups' => $eventGroups])
                    @error('editEvents') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <flux:checkbox wire:model="editIsActive" label="Active" />

                <div class="flex justify-end gap-3">
                    <flux:button variant="ghost" wire:click="$set('showEditModal', false)">Cancel</flux:button>
                    <flux:button variant="primary" type="submit">Save Changes</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    {{-- Outbound Delete Modal --}}
    <flux:modal wire:model="showDeleteModal">
        <div class="space-y-4">
            <flux:heading size="lg">Delete Webhook</flux:heading>
            <p class="text-sm text-zinc-600 dark:text-zinc-400">
                Are you sure you want to delete this webhook? Any pending deliveries will be cancelled and this action cannot be undone.
            </p>
            <div class="flex justify-end gap-3">
                <flux:button variant="ghost" wire:click="$set('showDeleteModal', false)">Cancel</flux:button>
                <flux:button variant="danger" wire:click="deleteWebhook">Delete Webhook</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Outbound Logs Modal --}}
    <flux:modal wire:model="showLogsModal" class="max-w-3xl">
        <div class="space-y-4">
            <flux:heading size="lg">Delivery Logs</flux:heading>

            @if (is_countable($outboundLogs) && count($outboundLogs) > 0)
                <div class="s-table-wrap max-h-96 overflow-y-auto">
                    <table class="s-table">
                        <thead>
                            <tr>
                                <th>Event</th>
                                <th>Response Code</th>
                                <th>Attempts</th>
                                <th>Delivered</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($outboundLogs as $log)
                                <tr wire:key="outbound-log-{{ $log->id }}">
                                    <td><span class="s-badge">{{ $log->event }}</span></td>
                                    <td>
                                        @if ($log->response_code && $log->response_code >= 200 && $log->response_code < 300)
                                            <span class="text-green-600 font-mono text-sm">{{ $log->response_code }}</span>
                                        @elseif ($log->response_code)
                                            <span class="text-red-600 font-mono text-sm">{{ $log->response_code }}</span>
                                        @else
                                            <span class="text-zinc-400 text-sm">--</span>
                                        @endif
                                    </td>
                                    <td class="text-sm text-zinc-500">{{ $log->attempts }}</td>
                                    <td>
                                        @if ($log->delivered_at)
                                            <span class="s-status s-status-green">Yes</span>
                                        @else
                                            <span class="s-status s-status-zinc">No</span>
                                        @endif
                                    </td>
                                    <td class="text-sm text-zinc-500">{{ $log->created_at->diffForHumans() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-6 text-center dark:border-zinc-700 dark:bg-zinc-800/50">
                    <p class="text-sm text-zinc-500">No delivery logs yet.</p>
                </div>
            @endif

            <div class="flex justify-end">
                <flux:button variant="ghost" wire:click="closeLogsModal">Close</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Inbound Create Modal --}}
    <flux:modal wire:model="showInboundCreateModal">
        <div class="space-y-6">
            <flux:heading size="lg">Create Inbound Source</flux:heading>

            <form wire:submit="createInboundSource" class="space-y-4">
                <flux:input wire:model.live="createInboundName" label="Name" placeholder="Stripe Payments" required />
                <flux:input wire:model.live="createInboundSlug" label="Slug" placeholder="stripe-payments" required />
                <flux:textarea wire:model="createInboundDescription" label="Description" placeholder="Optional description" />

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <flux:select wire:model="createInboundSignatureAlgo" label="Signature algorithm">
                        <option value="sha256">SHA-256</option>
                        <option value="sha512">SHA-512</option>
                    </flux:select>
                    <flux:input wire:model="createInboundSignatureHeader" label="Signature header" placeholder="X-Signature" required />
                </div>

                <flux:input wire:model="createInboundExpectedEvents" label="Expected events" placeholder="order.created, order.updated (optional, comma-separated)" />
                <flux:input wire:model="createInboundPriceWeight" type="number" min="0" max="4" label="Price weight (0–4)" required />
                <flux:checkbox wire:model="createInboundIsActive" label="Active" />

                <div class="flex justify-end gap-3">
                    <flux:button variant="ghost" wire:click="$set('showInboundCreateModal', false)">Cancel</flux:button>
                    <flux:button variant="primary" type="submit">Create Source</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    {{-- Inbound Edit Modal --}}
    <flux:modal wire:model="showInboundEditModal">
        <div class="space-y-6">
            <flux:heading size="lg">Edit Inbound Source</flux:heading>

            <form wire:submit="updateInboundSource" class="space-y-4">
                <flux:input wire:model="editInboundName" label="Name" required />
                <flux:input wire:model="editInboundSlug" label="Slug" required />
                <flux:textarea wire:model="editInboundDescription" label="Description" />

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <flux:select wire:model="editInboundSignatureAlgo" label="Signature algorithm">
                        <option value="sha256">SHA-256</option>
                        <option value="sha512">SHA-512</option>
                    </flux:select>
                    <flux:input wire:model="editInboundSignatureHeader" label="Signature header" required />
                </div>

                <flux:input wire:model="editInboundExpectedEvents" label="Expected events" placeholder="order.created, order.updated (optional, comma-separated)" />
                <flux:input wire:model="editInboundPriceWeight" type="number" min="0" max="4" label="Price weight (0–4)" required />
                <flux:checkbox wire:model="editInboundIsActive" label="Active" />

                <div class="flex justify-end gap-3">
                    <flux:button variant="ghost" wire:click="$set('showInboundEditModal', false)">Cancel</flux:button>
                    <flux:button variant="primary" type="submit">Save Changes</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    {{-- Inbound Delete Modal --}}
    <flux:modal wire:model="showInboundDeleteModal">
        <div class="space-y-4">
            <flux:heading size="lg">Delete Inbound Source</flux:heading>
            <p class="text-sm text-zinc-600 dark:text-zinc-400">
                Are you sure you want to delete this inbound source? Incoming webhooks to this URL will be rejected.
            </p>
            <div class="flex justify-end gap-3">
                <flux:button variant="ghost" wire:click="$set('showInboundDeleteModal', false)">Cancel</flux:button>
                <flux:button variant="danger" wire:click="deleteInboundSource">Delete Source</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Inbound Logs Modal --}}
    <flux:modal wire:model="showInboundLogsModal" class="max-w-3xl">
        <div class="space-y-4">
            <flux:heading size="lg">Inbound Delivery Logs</flux:heading>

            @if (is_countable($inboundLogs) && count($inboundLogs) > 0)
                <div class="s-table-wrap max-h-96 overflow-y-auto">
                    <table class="s-table">
                        <thead>
                            <tr>
                                <th>Event</th>
                                <th>Signature valid</th>
                                <th>Status code</th>
                                <th>Price weight</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($inboundLogs as $log)
                                <tr wire:key="inbound-log-{{ $log->id }}">
                                    <td><span class="s-badge">{{ $log->event ?? '—' }}</span></td>
                                    <td>
                                        @if ($log->signature_valid)
                                            <span class="s-status s-status-green">Valid</span>
                                        @else
                                            <span class="s-status s-status-red">Invalid</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($log->status_code && $log->status_code >= 200 && $log->status_code < 300)
                                            <span class="text-green-600 font-mono text-sm">{{ $log->status_code }}</span>
                                        @elseif ($log->status_code)
                                            <span class="text-red-600 font-mono text-sm">{{ $log->status_code }}</span>
                                        @else
                                            <span class="text-zinc-400 text-sm">--</span>
                                        @endif
                                    </td>
                                    <td class="text-sm text-zinc-500">{{ $log->price_weight }}</td>
                                    <td class="text-sm text-zinc-500">{{ $log->created_at->diffForHumans() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-6 text-center dark:border-zinc-700 dark:bg-zinc-800/50">
                    <p class="text-sm text-zinc-500">No inbound delivery logs yet.</p>
                </div>
            @endif

            <div class="flex justify-end">
                <flux:button variant="ghost" wire:click="closeInboundLogsModal">Close</flux:button>
            </div>
        </div>
    </flux:modal>
    </x-api::layout>
</section>
