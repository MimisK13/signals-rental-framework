<?php

use App\Actions\Notifications\DuplicateCommunicationTemplate;
use App\Actions\Notifications\RestoreCommunicationTemplateVersion;
use App\Actions\Notifications\SaveCommunicationTemplate;
use App\Data\Notifications\SaveCommunicationTemplateData;
use App\Models\CommunicationTemplate;
use App\Models\CommunicationTemplateVersion;
use App\Models\Opportunity;
use App\Services\Notifications\NotificationPreviewData;
use App\Services\Notifications\SmsSegmentCounter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Communication Template')] class extends Component {
    public CommunicationTemplate $template;

    public string $subject = '';

    public string $body = '';

    public string $fieldSearch = '';

    public string $previewSubject = '';

    public string $previewBody = '';

    public string $previewHtml = '';

    public string $previewMode = 'sample';

    public ?int $sourceOpportunityId = null;

    /** @var Collection<int, CommunicationTemplateVersion> */
    public Collection $versions;

    /** @var list<array{label: string, fields: list<array<string, mixed>>}> */
    public array $fieldGroups = [];

    /** @var list<array{id: int, label: string}> */
    public array $opportunityOptions = [];

    public function mount(CommunicationTemplate $communicationTemplate): void
    {
        Gate::authorize('view', $communicationTemplate);

        $this->template = $communicationTemplate->load(['notificationType', 'store']);
        $this->subject = (string) ($communicationTemplate->subject ?? '');
        $this->body = (string) $communicationTemplate->body;
        $this->fieldGroups = $this->buildFieldGroups();
        $this->loadVersions();
        $this->loadOpportunityOptions();
        $this->refreshPreview();
    }

    public function loadVersions(): void
    {
        $this->versions = $this->template->versions()->limit(20)->get();
    }

    public function loadOpportunityOptions(): void
    {
        if ($this->template->store_id === null) {
            $this->opportunityOptions = [];

            return;
        }

        $storeIds = auth()->user()?->accessibleStoreIds();

        if ($storeIds !== null && ! in_array($this->template->store_id, $storeIds, true)) {
            $this->opportunityOptions = [];

            return;
        }

        $this->opportunityOptions = Opportunity::query()
            ->where('store_id', $this->template->store_id)
            ->orderByDesc('updated_at')
            ->limit(25)
            ->get(['id', 'number', 'subject'])
            ->map(fn (Opportunity $opportunity): array => [
                'id' => $opportunity->id,
                'label' => trim($opportunity->number.' · '.$opportunity->subject),
            ])
            ->all();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['subject', 'body', 'previewMode', 'sourceOpportunityId'], true)) {
            $this->refreshPreview();
        }
    }

    public function insertField(string $path): void
    {
        $token = '{{ '.$path.' }}';
        $this->body = rtrim($this->body).($this->body === '' ? '' : ' ').$token;
        $this->refreshPreview();
    }

    public function save(): void
    {
        Gate::authorize('update', $this->template);

        if ($this->template->is_system) {
            $this->addError('template', 'System templates cannot be modified. Duplicate to customise.');

            return;
        }

        (new SaveCommunicationTemplate)($this->template, SaveCommunicationTemplateData::from([
            'name' => $this->template->name,
            'slug' => $this->template->slug,
            'channel' => $this->template->channel,
            'subject' => $this->subject,
            'body' => $this->body,
            'store_id' => $this->template->store_id,
            'notification_type_id' => $this->template->notification_type_id,
            'change_note' => 'Saved from communication template editor',
        ]));

        $this->template->refresh();
        $this->loadVersions();
        $this->refreshPreview();
        $this->dispatch('communication-template-saved');
    }

    public function duplicateTemplate(): void
    {
        Gate::authorize('update', $this->template);

        $duplicate = (new DuplicateCommunicationTemplate)($this->template);

        $this->redirect(route('admin.settings.communication-templates.edit', $duplicate->id), navigate: true);
    }

    public function restoreVersion(int $versionId): void
    {
        Gate::authorize('update', $this->template);

        $version = CommunicationTemplateVersion::query()->findOrFail($versionId);

        (new RestoreCommunicationTemplateVersion)($this->template, $version);

        $this->template->refresh();
        $this->subject = (string) ($this->template->subject ?? '');
        $this->body = (string) $this->template->body;
        $this->loadVersions();
        $this->refreshPreview();
        $this->dispatch('communication-template-version-restored');
    }

    public function refreshPreview(): void
    {
        $previewData = app(NotificationPreviewData::class);
        $mergeFields = app(\App\Services\Notifications\MergeFieldRenderer::class);

        $data = match ($this->previewMode) {
            'entity' => $this->previewDataFromEntity($previewData),
            default => $this->template->notificationType !== null
                ? $previewData->sampleForType($this->template->notificationType)
                : $previewData->sampleForType(new \App\Models\NotificationType(['data_schema' => ['groups' => []]])),
        };

        $this->previewSubject = $this->subject !== ''
            ? $mergeFields->render($this->subject, $data)
            : '';
        $this->previewBody = $mergeFields->render($this->body, $data);
        $this->previewHtml = $this->template->channel === 'email'
            ? Str::markdown($this->previewBody)
            : '';
    }

    /**
     * @return array{characters: int, segments: int, per_segment: int}
     */
    public function smsStats(): array
    {
        return app(SmsSegmentCounter::class)->analyse($this->body);
    }

    public function updatedFieldSearch(): void
    {
        $this->fieldGroups = $this->buildFieldGroups();
    }

    /**
     * @return list<array{label: string, fields: list<array<string, mixed>>}>
     */
    public function buildFieldGroups(): array
    {
        $notificationType = $this->template->notificationType;

        if ($notificationType === null) {
            return [];
        }

        $schema = $notificationType->data_schema;

        if (! is_array($schema)) {
            return [];
        }

        $schemaGroups = $schema['groups'] ?? [];

        if (! is_array($schemaGroups)) {
            return [];
        }

        $groups = [];

        foreach ($schemaGroups as $group) {
            if (! is_array($group)) {
                continue;
            }

            $fields = [];
            $groupFields = $group['fields'] ?? [];

            if (! is_array($groupFields)) {
                continue;
            }

            foreach ($groupFields as $field) {
                if (! is_array($field) || ! isset($field['path']) || ! is_string($field['path'])) {
                    continue;
                }

                $label = isset($field['label']) && is_string($field['label']) ? $field['label'] : '';

                if ($this->fieldSearch !== '' && ! str_contains(strtolower($field['path']), strtolower($this->fieldSearch))
                    && ! str_contains(strtolower($label), strtolower($this->fieldSearch))) {
                    continue;
                }

                $fields[] = $field;
            }

            if ($fields !== []) {
                $label = isset($group['label']) && is_string($group['label']) ? $group['label'] : 'Fields';

                $groups[] = [
                    'label' => $label,
                    'fields' => $fields,
                ];
            }
        }

        return $groups;
    }

    /**
     * @return array<string, mixed>
     */
    private function previewDataFromEntity(NotificationPreviewData $previewData): array
    {
        if ($this->sourceOpportunityId === null || $this->template->store_id === null) {
            return $this->template->notificationType !== null
                ? $previewData->sampleForType($this->template->notificationType)
                : [];
        }

        $storeIds = auth()->user()?->accessibleStoreIds();

        $opportunity = Opportunity::query()
            ->where('store_id', $this->template->store_id)
            ->when($storeIds !== null, fn ($query) => $query->whereIn('store_id', $storeIds))
            ->whereKey($this->sourceOpportunityId)
            ->first();

        if ($opportunity === null) {
            return $this->template->notificationType !== null
                ? $previewData->sampleForType($this->template->notificationType)
                : [];
        }

        return $previewData->fromOpportunity($opportunity);
    }
}; ?>

<section class="w-full">
    <x-admin.layout group="preferences" :wide="true" title="{{ $template->name }}" description="Edit communication template content and preview with live merge fields.">
        <x-slot:breadcrumbs>
            <x-signals.breadcrumb :items="[
                ['label' => 'Notifications', 'href' => route('admin.settings.notifications')],
                ['label' => 'Edit template'],
            ]" />
        </x-slot:breadcrumbs>

        <x-slot:actions>
            @if ($template->is_system)
                <flux:button variant="primary" wire:click="duplicateTemplate" wire:loading.attr="disabled">Duplicate to customise</flux:button>
            @else
                <flux:button variant="primary" wire:click="save" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="save">Save</span>
                    <span wire:loading wire:target="save">Saving…</span>
                </flux:button>
            @endif
        </x-slot:actions>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
            {{-- Editor + field browser --}}
            <div class="xl:col-span-2 space-y-4">
                <x-signals.card>
                    <div class="p-4 space-y-4">
                        @if ($template->channel === 'email')
                            <flux:input wire:model.live.debounce.400ms="subject" label="Subject" :disabled="$template->is_system" />
                        @endif

                        <flux:textarea wire:model.live.debounce.400ms="body" label="Body (Markdown)" rows="14" :disabled="$template->is_system" />

                        @if ($template->channel === 'sms')
                            @php $sms = $this->smsStats(); @endphp
                            <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                {{ $sms['characters'] }} characters · {{ $sms['segments'] }} segment(s)
                                @if ($sms['segments'] > 1)
                                    ({{ $sms['per_segment'] }} chars per concatenated segment)
                                @else
                                    (160 GSM-7 chars per segment)
                                @endif
                            </p>
                        @endif

                        @if ($template->is_system)
                            <p class="text-sm text-amber-600 dark:text-amber-400">System templates are read-only. Duplicate to create an editable copy.</p>
                        @endif
                    </div>
                </x-signals.card>

                <x-signals.card>
                    <div class="px-4 py-3 border-b border-zinc-200 dark:border-zinc-700">
                        <h3 class="text-sm font-semibold">Merge field browser</h3>
                    </div>
                    <div class="p-4 space-y-3">
                        <flux:input wire:model.live.debounce.300ms="fieldSearch" placeholder="Search fields…" />
                        @forelse ($fieldGroups as $group)
                            <div wire:key="group-{{ $group['label'] }}">
                                <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500 mb-2">{{ $group['label'] }}</div>
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($group['fields'] as $field)
                                        <button
                                            type="button"
                                            wire:key="field-{{ $field['path'] }}"
                                            wire:click="insertField('{{ $field['path'] }}')"
                                            class="s-btn s-btn-ghost s-btn-sm"
                                            @disabled($template->is_system)
                                        >
                                            {{ $field['label'] ?? $field['path'] }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-zinc-500">No merge fields defined for this notification type.</p>
                        @endforelse
                    </div>
                </x-signals.card>

                @if ($versions->isNotEmpty())
                    <x-signals.card>
                        <div class="px-4 py-3 border-b border-zinc-200 dark:border-zinc-700">
                            <h3 class="text-sm font-semibold">Version history</h3>
                        </div>
                        <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @foreach ($versions as $version)
                                <div wire:key="version-{{ $version->id }}" class="p-3 flex items-center justify-between gap-3 text-sm">
                                    <div>
                                        <span class="font-medium">v{{ $version->version }}</span>
                                        @if ($version->change_note)
                                            <span class="text-zinc-500">— {{ $version->change_note }}</span>
                                        @endif
                                    </div>
                                    @unless ($template->is_system)
                                        <button type="button" class="s-btn s-btn-ghost s-btn-sm" wire:click="restoreVersion({{ $version->id }})">Restore</button>
                                    @endunless
                                </div>
                            @endforeach
                        </div>
                    </x-signals.card>
                @endif
            </div>

            {{-- Live preview --}}
            <div class="space-y-4">
                <x-signals.card>
                    <div class="px-4 py-3 border-b border-zinc-200 dark:border-zinc-700 flex items-center justify-between">
                        <h3 class="text-sm font-semibold">Live preview</h3>
                    </div>
                    <div class="p-4 space-y-3">
                        <flux:select wire:model.live="previewMode" label="Preview data">
                            <flux:select.option value="sample">Sample data</flux:select.option>
                            <flux:select.option value="entity">Real entity</flux:select.option>
                        </flux:select>

                        @if ($previewMode === 'entity')
                            <flux:select wire:model.live="sourceOpportunityId" label="Preview with opportunity">
                                <flux:select.option value="">Select opportunity…</flux:select.option>
                                @foreach ($opportunityOptions as $option)
                                    <flux:select.option :value="$option['id']">{{ $option['label'] }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        @endif

                        @if ($template->channel === 'email' && $previewSubject !== '')
                            <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $previewSubject }}</div>
                        @endif

                        <div class="prose prose-sm dark:prose-invert max-w-none rounded-lg border border-zinc-200 dark:border-zinc-700 p-3 bg-zinc-50 dark:bg-zinc-900/40">
                            @if ($template->channel === 'email')
                                {!! $previewHtml !!}
                            @else
                                <pre class="whitespace-pre-wrap text-sm">{{ $previewBody }}</pre>
                            @endif
                        </div>
                    </div>
                </x-signals.card>
            </div>
        </div>
    </x-admin.layout>
</section>
