<?php

use App\Models\InboundWebhookLog;
use App\Models\InboundWebhookSource;
use App\Models\Webhook;
use App\Models\WebhookLog;
use App\Services\Api\WebhookEventRegistry;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Webhooks')] class extends Component
{
    #[Url]
    public string $tab = 'outbound';

    // Outbound modal states
    public bool $showCreateModal = false;

    public bool $showEditModal = false;

    public bool $showDeleteModal = false;

    public bool $showLogsModal = false;

    // Outbound create form
    public string $createUrl = '';

    /** @var list<string> */
    public array $createEvents = [];

    // Outbound edit form
    public ?int $editingWebhookId = null;

    public string $editUrl = '';

    /** @var list<string> */
    public array $editEvents = [];

    public bool $editIsActive = true;

    // Outbound delete
    public ?int $deletingWebhookId = null;

    // Outbound logs
    public ?int $viewingLogsWebhookId = null;

    // Outbound secret display (after creation)
    public bool $showOutboundSecret = false;

    public string $plainOutboundSecret = '';

    // Inbound modal states
    public bool $showInboundCreateModal = false;

    public bool $showInboundEditModal = false;

    public bool $showInboundDeleteModal = false;

    public bool $showInboundLogsModal = false;

    // Inbound create form
    public string $createInboundName = '';

    public string $createInboundSlug = '';

    public bool $createInboundSlugEdited = false;

    public string $createInboundDescription = '';

    public string $createInboundSignatureAlgo = 'sha256';

    public string $createInboundSignatureHeader = 'X-Signature';

    public string $createInboundExpectedEvents = '';

    public int $createInboundPriceWeight = 1;

    public bool $createInboundIsActive = true;

    // Inbound edit form
    public ?int $editingInboundSourceId = null;

    public string $editInboundName = '';

    public string $editInboundSlug = '';

    public string $editInboundDescription = '';

    public string $editInboundSignatureAlgo = 'sha256';

    public string $editInboundSignatureHeader = 'X-Signature';

    public string $editInboundExpectedEvents = '';

    public int $editInboundPriceWeight = 1;

    public bool $editInboundIsActive = true;

    // Inbound delete
    public ?int $deletingInboundSourceId = null;

    // Inbound logs
    public ?int $viewingInboundLogsSourceId = null;

    // Inbound secret display (after create/rotate)
    public bool $showInboundSecret = false;

    public string $plainInboundSecret = '';

    public function mount(): void
    {
        Gate::authorize('webhooks.manage');
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $outboundLogs = [];
        if ($this->viewingLogsWebhookId) {
            $outboundLogs = WebhookLog::query()
                ->where('webhook_id', $this->viewingLogsWebhookId)
                ->orderByDesc('created_at')
                ->limit(50)
                ->get();
        }

        $inboundLogs = [];
        if ($this->viewingInboundLogsSourceId) {
            $inboundLogs = InboundWebhookLog::query()
                ->where('inbound_webhook_source_id', $this->viewingInboundLogsSourceId)
                ->orderByDesc('created_at')
                ->limit(50)
                ->get();
        }

        return [
            'webhooks' => Webhook::query()
                ->with('user')
                ->latest()
                ->get(),
            'inboundSources' => InboundWebhookSource::query()
                ->latest()
                ->get(),
            'eventGroups' => $this->eventGroups(),
            'outboundLogs' => $outboundLogs,
            'inboundLogs' => $inboundLogs,
        ];
    }

    /**
     * Group a webhook's subscribed events by resource for compact display.
     *
     * @param  list<string>  $events
     * @return array<string, list<string>>
     */
    public function groupedEvents(array $events): array
    {
        $grouped = [];

        foreach ($events as $event) {
            if ($event === '*') {
                $grouped['All'][] = 'all';

                continue;
            }

            $key = str($event)->before('.')->toString();
            $action = str($event)->contains('.') ? str($event)->after('.')->toString() : $event;
            $grouped[(string) str($key)->headline()][] = $action;
        }

        return $grouped;
    }

    public function receiveUrl(string $slug): string
    {
        return '/webhooks/receive/'.$slug;
    }

    /**
     * @return array<string, array{label: string, events: list<string>}>
     */
    public function eventGroups(): array
    {
        return app(WebhookEventRegistry::class)->groups();
    }

    // --- Outbound actions ---

    public function openCreateModal(): void
    {
        $this->reset('createUrl', 'createEvents');
        $this->resetValidation();
        $this->showCreateModal = true;
    }

    public function createWebhook(): void
    {
        $this->validate([
            'createUrl' => ['required', 'url', 'max:2048'],
            'createEvents' => ['required', 'array', 'min:1'],
            'createEvents.*' => ['string', 'in:'.implode(',', [...app(WebhookEventRegistry::class)->names(), '*'])],
        ]);

        $secret = Str::random(32);

        Webhook::create([
            'user_id' => auth()->id(),
            'url' => $this->createUrl,
            'secret' => $secret,
            'events' => $this->createEvents,
            'is_active' => true,
            'consecutive_failures' => 0,
        ]);

        $this->showCreateModal = false;
        $this->plainOutboundSecret = $secret;
        $this->showOutboundSecret = true;

        $this->reset('createUrl', 'createEvents');
        $this->dispatch('webhook-created');
    }

    public function dismissOutboundSecret(): void
    {
        $this->showOutboundSecret = false;
        $this->plainOutboundSecret = '';
    }

    public function openEditModal(int $webhookId): void
    {
        $webhook = Webhook::findOrFail($webhookId);

        $this->editingWebhookId = $webhook->id;
        $this->editUrl = $webhook->url;
        $this->editEvents = $webhook->events;
        $this->editIsActive = $webhook->is_active;

        $this->resetValidation();
        $this->showEditModal = true;
    }

    public function updateWebhook(): void
    {
        $this->validate([
            'editUrl' => ['required', 'url', 'max:2048'],
            'editEvents' => ['required', 'array', 'min:1'],
            'editEvents.*' => ['string', 'in:'.implode(',', [...app(WebhookEventRegistry::class)->names(), '*'])],
        ]);

        $webhook = Webhook::findOrFail($this->editingWebhookId);

        $webhook->update([
            'url' => $this->editUrl,
            'events' => $this->editEvents,
            'is_active' => $this->editIsActive,
        ]);

        $this->showEditModal = false;
        $this->editingWebhookId = null;
        $this->dispatch('webhook-updated');
    }

    public function confirmDelete(int $webhookId): void
    {
        $this->deletingWebhookId = $webhookId;
        $this->showDeleteModal = true;
    }

    public function deleteWebhook(): void
    {
        if ($this->deletingWebhookId) {
            Webhook::where('id', $this->deletingWebhookId)->delete();
        }

        $this->showDeleteModal = false;
        $this->deletingWebhookId = null;
        $this->dispatch('webhook-deleted');
    }

    public function reenableWebhook(int $webhookId): void
    {
        $webhook = Webhook::findOrFail($webhookId);

        $webhook->update([
            'is_active' => true,
            'consecutive_failures' => 0,
            'disabled_at' => null,
        ]);

        $this->dispatch('webhook-reenabled');
    }

    public function viewLogs(int $webhookId): void
    {
        $this->viewingLogsWebhookId = $webhookId;
        $this->showLogsModal = true;
    }

    public function closeLogsModal(): void
    {
        $this->showLogsModal = false;
        $this->viewingLogsWebhookId = null;
    }

    // --- Inbound actions ---

    public function updatedCreateInboundName(string $value): void
    {
        if (! $this->createInboundSlugEdited) {
            $this->createInboundSlug = Str::slug($value);
        }
    }

    public function updatedCreateInboundSlug(): void
    {
        $this->createInboundSlugEdited = true;
    }

    /**
     * @return list<string>
     */
    private function parseExpectedEvents(string $value): array
    {
        if (trim($value) === '') {
            return [];
        }

        return collect(explode(',', $value))
            ->map(fn (string $event) => trim($event))
            ->filter()
            ->values()
            ->all();
    }

    public function openInboundCreateModal(): void
    {
        $this->reset(
            'createInboundName',
            'createInboundSlug',
            'createInboundSlugEdited',
            'createInboundDescription',
            'createInboundSignatureAlgo',
            'createInboundSignatureHeader',
            'createInboundExpectedEvents',
            'createInboundPriceWeight',
            'createInboundIsActive',
        );
        $this->createInboundSignatureAlgo = 'sha256';
        $this->createInboundSignatureHeader = 'X-Signature';
        $this->createInboundPriceWeight = 1;
        $this->createInboundIsActive = true;
        $this->resetValidation();
        $this->showInboundCreateModal = true;
    }

    public function createInboundSource(): void
    {
        $this->validate([
            'createInboundName' => ['required', 'string', 'max:255'],
            'createInboundSlug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('inbound_webhook_sources', 'slug')],
            'createInboundDescription' => ['nullable', 'string', 'max:65535'],
            'createInboundSignatureAlgo' => ['required', 'string', Rule::in(['sha256', 'sha512'])],
            'createInboundSignatureHeader' => ['required', 'string', 'max:255'],
            'createInboundPriceWeight' => ['required', 'integer', 'min:0', 'max:4'],
            'createInboundIsActive' => ['boolean'],
        ]);

        $secret = Str::random(32);

        InboundWebhookSource::create([
            'name' => $this->createInboundName,
            'slug' => $this->createInboundSlug,
            'description' => $this->createInboundDescription ?: null,
            'secret' => $secret,
            'signature_algo' => $this->createInboundSignatureAlgo,
            'signature_header' => $this->createInboundSignatureHeader,
            'expected_events' => $this->parseExpectedEvents($this->createInboundExpectedEvents),
            'price_weight' => $this->createInboundPriceWeight,
            'is_active' => $this->createInboundIsActive,
        ]);

        $this->showInboundCreateModal = false;
        $this->plainInboundSecret = $secret;
        $this->showInboundSecret = true;

        $this->reset(
            'createInboundName',
            'createInboundSlug',
            'createInboundSlugEdited',
            'createInboundDescription',
            'createInboundExpectedEvents',
        );
        $this->dispatch('inbound-source-created');
    }

    public function dismissInboundSecret(): void
    {
        $this->showInboundSecret = false;
        $this->plainInboundSecret = '';
    }

    public function openInboundEditModal(int $sourceId): void
    {
        $source = InboundWebhookSource::findOrFail($sourceId);

        $this->editingInboundSourceId = $source->id;
        $this->editInboundName = $source->name;
        $this->editInboundSlug = $source->slug;
        $this->editInboundDescription = $source->description ?? '';
        $this->editInboundSignatureAlgo = $source->signature_algo;
        $this->editInboundSignatureHeader = $source->signature_header;
        $this->editInboundExpectedEvents = implode(', ', $source->expected_events ?? []);
        $this->editInboundPriceWeight = $source->price_weight;
        $this->editInboundIsActive = $source->is_active;

        $this->resetValidation();
        $this->showInboundEditModal = true;
    }

    public function updateInboundSource(): void
    {
        $this->validate([
            'editInboundName' => ['required', 'string', 'max:255'],
            'editInboundSlug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('inbound_webhook_sources', 'slug')->ignore($this->editingInboundSourceId),
            ],
            'editInboundDescription' => ['nullable', 'string', 'max:65535'],
            'editInboundSignatureAlgo' => ['required', 'string', Rule::in(['sha256', 'sha512'])],
            'editInboundSignatureHeader' => ['required', 'string', 'max:255'],
            'editInboundPriceWeight' => ['required', 'integer', 'min:0', 'max:4'],
            'editInboundIsActive' => ['boolean'],
        ]);

        $source = InboundWebhookSource::findOrFail($this->editingInboundSourceId);

        $source->update([
            'name' => $this->editInboundName,
            'slug' => $this->editInboundSlug,
            'description' => $this->editInboundDescription ?: null,
            'signature_algo' => $this->editInboundSignatureAlgo,
            'signature_header' => $this->editInboundSignatureHeader,
            'expected_events' => $this->parseExpectedEvents($this->editInboundExpectedEvents),
            'price_weight' => $this->editInboundPriceWeight,
            'is_active' => $this->editInboundIsActive,
        ]);

        $this->showInboundEditModal = false;
        $this->editingInboundSourceId = null;
        $this->dispatch('inbound-source-updated');
    }

    public function confirmInboundDelete(int $sourceId): void
    {
        $this->deletingInboundSourceId = $sourceId;
        $this->showInboundDeleteModal = true;
    }

    public function deleteInboundSource(): void
    {
        if ($this->deletingInboundSourceId) {
            InboundWebhookSource::where('id', $this->deletingInboundSourceId)->delete();
        }

        $this->showInboundDeleteModal = false;
        $this->deletingInboundSourceId = null;
        $this->dispatch('inbound-source-deleted');
    }

    public function rotateInboundSecret(int $sourceId): void
    {
        $source = InboundWebhookSource::findOrFail($sourceId);
        $secret = Str::random(32);

        $source->update(['secret' => $secret]);

        $this->plainInboundSecret = $secret;
        $this->showInboundSecret = true;
        $this->dispatch('inbound-secret-rotated');
    }

    public function toggleInboundActive(int $sourceId): void
    {
        $source = InboundWebhookSource::findOrFail($sourceId);

        $source->update(['is_active' => ! $source->is_active]);

        $this->dispatch('inbound-source-toggled');
    }

    public function viewInboundLogs(int $sourceId): void
    {
        $this->viewingInboundLogsSourceId = $sourceId;
        $this->showInboundLogsModal = true;
    }

    public function closeInboundLogsModal(): void
    {
        $this->showInboundLogsModal = false;
        $this->viewingInboundLogsSourceId = null;
    }
}; ?>

<section class="w-full">
    <x-api.layout :wide="true" title="Webhooks" description="Manage outbound subscriptions and inbound webhook sources">
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
                                    <div class="flex items-center gap-1">
                                        <button class="s-btn s-btn-ghost s-btn-sm" wire:click="viewLogs({{ $webhook->id }})" title="View delivery logs">
                                            <flux:icon.document-text class="w-4 h-4" />
                                        </button>
                                        <button class="s-btn s-btn-ghost s-btn-sm" wire:click="openEditModal({{ $webhook->id }})" title="Edit webhook">
                                            <flux:icon.pencil class="w-4 h-4" />
                                        </button>
                                        @unless ($webhook->is_active)
                                            <button class="s-btn s-btn-ghost s-btn-sm text-green-600 hover:text-green-700" wire:click="reenableWebhook({{ $webhook->id }})" title="Re-enable webhook">
                                                <flux:icon.arrow-path class="w-4 h-4" />
                                            </button>
                                        @endunless
                                        <button class="s-btn s-btn-ghost s-btn-sm text-red-600 hover:text-red-700" wire:click="confirmDelete({{ $webhook->id }})" title="Delete webhook">
                                            <flux:icon.trash class="w-4 h-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <x-signals.card>
                <div class="py-8 text-center">
                    <flux:icon.signal class="mx-auto h-10 w-10 text-zinc-400" />
                    <p class="mt-3 text-sm font-medium text-zinc-900 dark:text-zinc-100">No webhooks</p>
                    <p class="mt-1 text-sm text-zinc-500">Create a webhook to receive event notifications at an external URL.</p>
                </div>
            </x-signals.card>
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
                                    <div class="flex items-center gap-1">
                                        <button class="s-btn s-btn-ghost s-btn-sm" wire:click="viewInboundLogs({{ $source->id }})" title="View delivery logs">
                                            <flux:icon.document-text class="w-4 h-4" />
                                        </button>
                                        <button class="s-btn s-btn-ghost s-btn-sm" wire:click="openInboundEditModal({{ $source->id }})" title="Edit source">
                                            <flux:icon.pencil class="w-4 h-4" />
                                        </button>
                                        <button class="s-btn s-btn-ghost s-btn-sm" wire:click="rotateInboundSecret({{ $source->id }})" title="Rotate secret">
                                            <flux:icon.arrow-path class="w-4 h-4" />
                                        </button>
                                        <button class="s-btn s-btn-ghost s-btn-sm" wire:click="toggleInboundActive({{ $source->id }})" title="Toggle active">
                                            <flux:icon.power class="w-4 h-4" />
                                        </button>
                                        <button class="s-btn s-btn-ghost s-btn-sm text-red-600 hover:text-red-700" wire:click="confirmInboundDelete({{ $source->id }})" title="Delete source">
                                            <flux:icon.trash class="w-4 h-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <x-signals.card>
                <div class="py-8 text-center">
                    <flux:icon.arrow-down-tray class="mx-auto h-10 w-10 text-zinc-400" />
                    <p class="mt-3 text-sm font-medium text-zinc-900 dark:text-zinc-100">No inbound sources</p>
                    <p class="mt-1 text-sm text-zinc-500">Create a source to receive signed webhooks from external systems.</p>
                </div>
            </x-signals.card>
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
                    @include('livewire.api.partials.event-picker', ['model' => 'createEvents', 'groups' => $eventGroups])
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
                    @include('livewire.api.partials.event-picker', ['model' => 'editEvents', 'groups' => $eventGroups])
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
    </x-api.layout>
</section>
