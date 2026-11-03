<?php

declare(strict_types=1);

namespace App\Domain\Webhooks\Presentation\Livewire;

use App\Domain\Webhooks\Models\InboundWebhookLog;
use App\Domain\Webhooks\Models\InboundWebhookSource;
use App\Domain\Webhooks\Models\Webhook;
use App\Domain\Webhooks\Models\WebhookLog;
use App\Domain\Webhooks\Services\WebhookEventRegistry;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')] #[Title('Webhooks')] final class Index extends Component
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

        $userId = auth()->id();
        abort_unless(is_int($userId), 403);

        Webhook::create([
            'user_id' => $userId,
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

    public function render(): View
    {
        return view('webhooks::index', $this->with());
    }
}
