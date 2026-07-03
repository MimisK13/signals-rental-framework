<?php

use App\Actions\Documents\RestoreDocumentTemplateVersion;
use App\Actions\Documents\UpdateDocumentSequence;
use App\Actions\Documents\UpdateDocumentTemplate;
use App\Contracts\Documents\DocumentRenderer;
use App\Data\Documents\UpdateDocumentSequenceData;
use App\Data\Documents\UpdateDocumentTemplateData;
use App\Enums\DocumentOutputFormat;
use App\Models\DocumentSequence;
use App\Models\DocumentTemplate;
use App\Models\DocumentTemplateVersion;
use App\Models\Opportunity;
use App\Services\Documents\DocumentFieldSnippetBuilder;
use App\Services\Documents\DocumentResolverRegistry;
use App\Services\Documents\DocumentSampleData;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('components.layouts.editor')] #[Title('Edit Document Template')] class extends Component {
    use WithFileUploads;

    public DocumentTemplate $template;

    public string $bodyHtml = '';

    public string $headerHtml = '';

    public string $footerHtml = '';

    public string $stylesCss = '';

    public string $activeTab = 'body';

    public string $fieldSearch = '';

    public string $previewHtml = '';

    public string $previewError = '';

    public string $previewMode = 'sample';

    public ?int $sourceOpportunityId = null;

    public int $previewZoom = 100;

    public bool $showSettingsPanel = false;

    /** @var array<string, mixed> */
    public array $pageConfig = [];

    /** @var array<string, mixed> */
    public array $brandingConfig = [];

    public string $sequencePrefix = '';

    public string $sequenceSuffix = '';

    public bool $sequenceIncludeYear = false;

    public bool $sequenceIncludeMonth = false;

    public int $sequenceNextNumber = 1;

    public int $sequencePadding = 4;

    public ?DocumentSequence $sequence = null;

    /** @var Collection<int, DocumentTemplateVersion> */
    public Collection $versions;

    /** @var list<array{name: string, fields: list<array<string, mixed>>}> */
    public array $fieldTree = [];

    /** @var list<array{id: int, label: string}> */
    public array $opportunityOptions = [];

    public $logoUpload = null;

    public function mount(DocumentTemplate $documentTemplate): void
    {
        Gate::authorize('view', $documentTemplate);

        $this->template = $documentTemplate->load('store');
        $this->bodyHtml = (string) $documentTemplate->body_html;
        $this->headerHtml = (string) ($documentTemplate->header_html ?? '');
        $this->footerHtml = (string) ($documentTemplate->footer_html ?? '');
        $this->stylesCss = (string) ($documentTemplate->styles_css ?? '');
        $this->pageConfig = $documentTemplate->page_config ?? config('documents.defaults.page', []);
        $this->brandingConfig = $documentTemplate->branding_config ?? [];

        $this->fieldTree = app(DocumentResolverRegistry::class)->fieldTree($documentTemplate->resolverType());
        $this->loadVersions();
        $this->loadSequence();
        $this->loadOpportunityOptions();
        $this->refreshPreview();
    }

    public function loadVersions(): void
    {
        $this->versions = $this->template->versions()->limit(20)->get();
    }

    public function loadSequence(): void
    {
        $this->sequence = DocumentSequence::query()
            ->where('store_id', $this->template->store_id)
            ->where('document_type', $this->template->document_type)
            ->first();

        if ($this->sequence === null) {
            return;
        }

        $this->sequencePrefix = (string) ($this->sequence->prefix ?? '');
        $this->sequenceSuffix = (string) ($this->sequence->suffix ?? '');
        $this->sequenceIncludeYear = (bool) $this->sequence->include_year;
        $this->sequenceIncludeMonth = (bool) $this->sequence->include_month;
        $this->sequenceNextNumber = (int) $this->sequence->next_number;
        $this->sequencePadding = (int) $this->sequence->padding;
    }

    public function loadOpportunityOptions(): void
    {
        $query = Opportunity::query()
            ->where('store_id', $this->template->store_id)
            ->orderByDesc('updated_at')
            ->limit(25);

        $storeIds = auth()->user()?->accessibleStoreIds();

        if ($storeIds !== null && ! in_array($this->template->store_id, $storeIds, true)) {
            $this->opportunityOptions = [];

            return;
        }

        $this->opportunityOptions = $query
            ->get(['id', 'number', 'subject'])
            ->map(fn (Opportunity $opportunity): array => [
                'id' => $opportunity->id,
                'label' => trim($opportunity->number.' · '.$opportunity->subject),
            ])
            ->all();
    }

    public function updated($property): void
    {
        if (in_array($property, ['bodyHtml', 'headerHtml', 'footerHtml', 'stylesCss', 'previewMode', 'sourceOpportunityId'], true)) {
            $this->refreshPreview();
        }
    }

    public function setActiveTab(string $tab): void
    {
        if (! in_array($tab, ['body', 'header', 'footer', 'styles'], true)) {
            return;
        }

        $this->activeTab = $tab;
    }

    public function insertField(string $fieldName, string $type): void
    {
        $snippet = app(DocumentFieldSnippetBuilder::class)->insertSnippet($fieldName, $type);
        $this->insertIntoActiveTab($snippet);
    }

    public function insertSnippet(string $snippet): void
    {
        $this->insertIntoActiveTab($snippet);
    }

    public function insertLineItemRowSnippet(): void
    {
        $this->insertSnippet("@foreach (\$items as \$item)\n    <tr><td>{{ \$item->name }}</td></tr>\n@endforeach");
    }

    public function save(): void
    {
        (new UpdateDocumentTemplate)($this->template, new UpdateDocumentTemplateData(
            body_html: $this->bodyHtml,
            header_html: $this->headerHtml,
            footer_html: $this->footerHtml,
            styles_css: $this->stylesCss,
            change_note: 'Saved from template editor',
        ));

        $this->template->refresh();
        $this->loadVersions();
        $this->refreshPreview();
        $this->dispatch('document-template-saved');
    }

    public function saveSettings(): void
    {
        Gate::authorize('update', $this->template);

        (new UpdateDocumentTemplate)($this->template, new UpdateDocumentTemplateData(
            page_config: $this->pageConfig,
            branding_config: $this->brandingConfig,
            change_note: 'Updated page and branding settings',
        ));

        if ($this->sequence !== null) {
            Gate::authorize('update', $this->sequence);

            (new UpdateDocumentSequence)($this->sequence, new UpdateDocumentSequenceData(
                prefix: $this->sequencePrefix,
                suffix: $this->sequenceSuffix !== '' ? $this->sequenceSuffix : null,
                include_year: $this->sequenceIncludeYear,
                include_month: $this->sequenceIncludeMonth,
                next_number: $this->sequenceNextNumber,
                padding: $this->sequencePadding,
            ));

            $this->loadSequence();
        }

        $this->template->refresh();
        $this->refreshPreview();
        $this->showSettingsPanel = false;
        $this->dispatch('document-template-settings-saved');
    }

    public function restoreVersion(int $versionId): void
    {
        $version = DocumentTemplateVersion::query()->findOrFail($versionId);
        (new RestoreDocumentTemplateVersion)($this->template, $version);

        $this->template->refresh();
        $this->bodyHtml = (string) $this->template->body_html;
        $this->headerHtml = (string) ($this->template->header_html ?? '');
        $this->footerHtml = (string) ($this->template->footer_html ?? '');
        $this->stylesCss = (string) ($this->template->styles_css ?? '');
        $this->pageConfig = $this->template->page_config ?? config('documents.defaults.page', []);
        $this->loadVersions();
        $this->refreshPreview();
        $this->dispatchEditorSync();
        $this->dispatch('document-template-version-restored');
    }

    public function updatedLogoUpload(): void
    {
        $this->validate([
            'logoUpload' => ['required', 'image', 'max:2048', 'mimes:png,jpg,jpeg,svg,webp'],
        ]);

        $path = $this->logoUpload->store('document-templates/'.$this->template->id, 'public');

        if ($path === false) {
            $this->addError('logoUpload', 'Failed to upload logo.');

            return;
        }

        $this->brandingConfig['logo_path'] = $path;
        $this->logoUpload = null;
    }

    public function refreshPreview(): void
    {
        $this->previewError = '';
        $this->previewHtml = '';

        try {
            $renderedData = $this->resolvePreviewData();

            $result = app(DocumentRenderer::class)->renderFromSnapshot(
                $this->previewTemplate(),
                $renderedData,
                DocumentOutputFormat::Preview,
            );

            if (! $result->success) {
                $this->previewError = $result->error ?? 'Unable to render preview.';

                return;
            }

            $this->previewHtml = $result->html ?? '';
        } catch (ValidationException $exception) {
            $this->previewError = collect($exception->errors())->flatten()->first()
                ?? 'Unable to render preview.';
        }
    }

    /**
     * @return list<array{name: string, fields: list<array<string, mixed>>}>
     */
    public function filteredFieldTree(): array
    {
        $needle = strtolower(trim($this->fieldSearch));

        if ($needle === '') {
            return $this->fieldTree;
        }

        $filtered = [];

        foreach ($this->fieldTree as $group) {
            $fields = array_values(array_filter(
                $group['fields'],
                fn (array $field): bool => str_contains(strtolower((string) ($field['label'] ?? $field['name'])), $needle)
                    || str_contains(strtolower((string) $field['name']), $needle),
            ));

            if ($fields !== []) {
                $filtered[] = [
                    'name' => $group['name'],
                    'fields' => $fields,
                ];
            }
        }

        return $filtered;
    }

    private function insertIntoActiveTab(string $snippet): void
    {
        match ($this->activeTab) {
            'header' => $this->headerHtml = rtrim($this->headerHtml)."\n".$snippet,
            'footer' => $this->footerHtml = rtrim($this->footerHtml)."\n".$snippet,
            'styles' => $this->stylesCss = rtrim($this->stylesCss)."\n".$snippet,
            default => $this->bodyHtml = rtrim($this->bodyHtml)."\n".$snippet,
        };

        $this->refreshPreview();
        $this->dispatchEditorSync();
    }

    private function dispatchEditorSync(): void
    {
        $this->dispatch('document-template-editor-sync');
    }

    private function previewTemplate(): DocumentTemplate
    {
        $preview = $this->template->replicate();
        $preview->id = $this->template->id;
        $preview->version = $this->template->version;
        $preview->setRelation('store', $this->template->store);
        $preview->fill([
            'body_html' => $this->bodyHtml,
            'header_html' => $this->headerHtml,
            'footer_html' => $this->footerHtml,
            'styles_css' => $this->stylesCss,
            'page_config' => $this->pageConfig,
            'branding_config' => $this->brandingConfig,
        ]);

        return $preview;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolvePreviewData(): array
    {
        if ($this->previewMode === 'real') {
            if ($this->sourceOpportunityId === null) {
                throw ValidationException::withMessages([
                    'sourceOpportunityId' => ['Select an opportunity for real-data preview.'],
                ]);
            }

            $opportunity = Opportunity::query()->findOrFail($this->sourceOpportunityId);
            Gate::authorize('view', $opportunity);

            if ($opportunity->store_id !== $this->template->store_id) {
                throw ValidationException::withMessages([
                    'sourceOpportunityId' => ['The selected opportunity does not belong to this template store.'],
                ]);
            }

            return app(DocumentResolverRegistry::class)->resolve('opportunity', $opportunity->id);
        }

        return DocumentSampleData::opportunity();
    }
}; ?>

<style>
    .dt-editor { display: flex; flex-direction: column; height: 100vh; background: var(--content-bg); color: var(--text-primary); }
    .dt-topbar { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 16px; border-bottom: 1px solid var(--card-border); background: var(--navy); color: #fff; }
    .dt-topbar h1 { font-size: 14px; font-weight: 600; margin: 0; }
    .dt-main { display: flex; min-height: 0; flex: 1; }
    .dt-sidebar { width: 280px; min-width: 280px; border-right: 1px solid var(--card-border); background: var(--card-bg); display: flex; flex-direction: column; }
    .dt-sidebar-header, .dt-preview-header { padding: 12px 14px; border-bottom: 1px solid var(--card-border); font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-muted); }
    .dt-field-groups { overflow: auto; flex: 1; padding: 8px; }
    .dt-field-group { margin-bottom: 12px; }
    .dt-field-group-title { font-size: 11px; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px; }
    .dt-field-btn { display: block; width: 100%; text-align: left; padding: 6px 8px; border: 1px solid transparent; background: transparent; color: var(--text-primary); font-size: 12px; cursor: pointer; }
    .dt-field-btn:hover { background: rgba(0,0,0,0.04); border-color: var(--card-border); }
    .dt-center { flex: 1; min-width: 0; display: flex; flex-direction: column; border-right: 1px solid var(--card-border); }
    .dt-tabs { display: flex; gap: 4px; padding: 8px 12px; border-bottom: 1px solid var(--card-border); background: var(--card-bg); }
    .dt-tab { padding: 6px 10px; font-size: 12px; border: 1px solid transparent; background: transparent; cursor: pointer; color: var(--text-secondary); }
    .dt-tab.active { border-color: var(--card-border); background: var(--base); color: var(--text-primary); }
    .dt-code { flex: 1; min-height: 0; padding: 12px; display: flex; flex-direction: column; }
    .dt-cm-host { flex: 1; min-height: 420px; border: 1px solid var(--card-border); border-radius: 4px; overflow: hidden; }
    .dt-cm-host .cm-editor { height: 100%; }
    .dt-code textarea { width: 100%; height: 100%; min-height: 420px; resize: none; font-family: var(--font-mono); font-size: 12px; line-height: 1.5; padding: 12px; border: 1px solid var(--card-border); background: var(--base); color: var(--text-primary); }
    .dt-preview { width: 42%; min-width: 360px; display: flex; flex-direction: column; background: var(--card-bg); }
    .dt-preview-controls { display: flex; flex-wrap: wrap; gap: 8px; padding: 10px 12px; border-bottom: 1px solid var(--card-border); }
    .dt-preview-frame-wrap { flex: 1; overflow: auto; padding: 16px; background: #e5e7eb; }
    .dark .dt-preview-frame-wrap { background: #111827; }
    .dt-preview-paper { margin: 0 auto; background: #fff; color: #111; box-shadow: 0 8px 24px rgba(0,0,0,0.12); transform-origin: top center; }
    .dt-preview-error { padding: 12px; color: var(--red); font-size: 13px; }
    .dt-settings-panel { position: fixed; inset: 0 0 0 auto; width: min(420px, 100vw); background: var(--card-bg); border-left: 1px solid var(--card-border); z-index: 50; overflow: auto; padding: 16px; box-shadow: -8px 0 24px rgba(0,0,0,0.12); }
    .dt-settings-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,0.35); z-index: 40; }
    .dt-versions { margin-top: 16px; border-top: 1px solid var(--card-border); padding-top: 12px; }
</style>

<div class="dt-editor" data-testid="document-template-editor">
    <div class="dt-topbar">
        <div class="flex items-center gap-3 min-w-0">
            <a href="{{ route('admin.document-templates.index') }}" wire:navigate class="s-btn s-btn-ghost s-btn-sm text-white/80">Back</a>
            <div class="min-w-0">
                <h1>{{ $template->name }}</h1>
                <p class="text-xs text-white/70 truncate">{{ $template->store?->name }} · {{ str($template->document_type)->headline() }} · v{{ $template->version }}</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" wire:click="$set('showSettingsPanel', true)" class="s-btn s-btn-ghost s-btn-sm text-white/90">Page & numbering</button>
            @can('update', $template)
                <flux:button variant="primary" wire:click="save" wire:loading.attr="disabled">Save</flux:button>
            @endcan
        </div>
    </div>

    <div class="dt-main">
        <aside class="dt-sidebar" data-testid="field-browser">
            <div class="dt-sidebar-header">Field browser</div>
            <div class="p-3 border-b border-[var(--card-border)]">
                <flux:input wire:model.live.debounce.300ms="fieldSearch" placeholder="Search fields..." />
            </div>
            <div class="dt-field-groups">
                @foreach($this->filteredFieldTree() as $group)
                    <div class="dt-field-group" wire:key="field-group-{{ $group['name'] }}">
                        <div class="dt-field-group-title">{{ $group['name'] }}</div>
                        @foreach($group['fields'] as $field)
                            <button type="button"
                                    wire:key="field-{{ $field['name'] }}"
                                    wire:click="insertField(@js($field['name']), @js($field['type']))"
                                    class="dt-field-btn">
                                {{ $field['label'] ?? $field['name'] }}
                            </button>
                        @endforeach
                    </div>
                @endforeach

                <div class="dt-field-group">
                    <div class="dt-field-group-title">Snippets</div>
                    <button type="button" wire:click="insertLineItemRowSnippet" class="dt-field-btn">Line item table row</button>
                </div>
            </div>
        </aside>

        <section class="dt-center" data-testid="code-editor">
            <div class="dt-tabs">
                @foreach(['body' => 'Body', 'header' => 'Header', 'footer' => 'Footer', 'styles' => 'Styles'] as $tab => $label)
                    <button type="button" wire:click="setActiveTab('{{ $tab }}')" class="dt-tab {{ $activeTab === $tab ? 'active' : '' }}">{{ $label }}</button>
                @endforeach
            </div>
            <div class="dt-code"
                 x-data="{
                     ...window.signals.documentTemplateEditor({
                         wire: $wire,
                         contents: {
                             body: @js($bodyHtml),
                             header: @js($headerHtml),
                             footer: @js($footerHtml),
                             styles: @js($stylesCss),
                         },
                     }),
                     activeTab: @entangle('activeTab').live,
                 }"
                 x-init="init()"
                 @destroy.window="destroy()">
                <div x-show="cmReady"
                     x-ref="cmHost"
                     data-testid="codemirror-host"
                     class="dt-cm-host"
                     wire:ignore></div>
                <div x-show="!cmReady">
                    @if($activeTab === 'body')
                        <textarea wire:model.live.debounce.500ms="bodyHtml" spellcheck="false" aria-label="Body editor"></textarea>
                    @elseif($activeTab === 'header')
                        <textarea wire:model.live.debounce.500ms="headerHtml" spellcheck="false" aria-label="Header editor"></textarea>
                    @elseif($activeTab === 'footer')
                        <textarea wire:model.live.debounce.500ms="footerHtml" spellcheck="false" aria-label="Footer editor"></textarea>
                    @else
                        <textarea wire:model.live.debounce.500ms="stylesCss" spellcheck="false" aria-label="Styles editor"></textarea>
                    @endif
                    <p class="mt-2 text-xs text-[var(--text-muted)]">CodeMirror unavailable — using plain textarea fallback.</p>
                </div>
            </div>
        </section>

        <aside class="dt-preview" data-testid="preview-panel">
            <div class="dt-preview-header">Live preview</div>
            <div class="dt-preview-controls">
                <flux:select wire:model.live="previewMode" class="min-w-[140px]">
                    <flux:select.option value="sample">Sample data</flux:select.option>
                    <flux:select.option value="real">Real opportunity</flux:select.option>
                </flux:select>
                @if($previewMode === 'real')
                    <flux:select wire:model.live="sourceOpportunityId" placeholder="Select opportunity..." class="min-w-[220px]">
                        <flux:select.option value="">Select opportunity...</flux:select.option>
                        @foreach($opportunityOptions as $option)
                            <flux:select.option value="{{ $option['id'] }}">{{ $option['label'] }}</flux:select.option>
                        @endforeach
                    </flux:select>
                @endif
                <flux:select wire:model.live="previewZoom" class="w-[100px]">
                    <flux:select.option value="75">75%</flux:select.option>
                    <flux:select.option value="100">100%</flux:select.option>
                    <flux:select.option value="125">125%</flux:select.option>
                </flux:select>
            </div>

            @if($previewError !== '')
                <div class="dt-preview-error">{{ $previewError }}</div>
            @elseif($previewHtml !== '')
                <div class="dt-preview-frame-wrap">
                    <div class="dt-preview-paper" style="width: 210mm; min-height: 297mm; transform: scale({{ $previewZoom / 100 }});">
                        <iframe title="Document preview"
                                data-testid="preview-iframe"
                                class="w-full min-h-[297mm] border-0 bg-white"
                                srcdoc="{{ e($previewHtml) }}"></iframe>
                    </div>
                </div>
            @else
                <div wire:loading wire:target="refreshPreview,bodyHtml,headerHtml,footerHtml,stylesCss,previewMode,sourceOpportunityId" class="p-4 text-sm text-[var(--text-muted)]">
                    Rendering preview...
                </div>
            @endif

            <div class="dt-versions px-3 pb-4">
                <h3 class="text-sm font-semibold mb-2">Version history</h3>
                @forelse($versions as $version)
                    <div class="flex items-center justify-between gap-2 py-2 border-b border-[var(--card-border)]" wire:key="version-{{ $version->id }}">
                        <div class="text-sm">
                            <span class="font-medium">v{{ $version->version }}</span>
                            @if($version->change_note)
                                <span class="text-[var(--text-muted)]"> — {{ $version->change_note }}</span>
                            @endif
                        </div>
                        @can('update', $template)
                            <button type="button"
                                    wire:click="restoreVersion({{ $version->id }})"
                                    wire:confirm="Restore version {{ $version->version }}?"
                                    class="s-btn s-btn-ghost s-btn-xs">
                                Restore
                            </button>
                        @endcan
                    </div>
                @empty
                    <p class="text-sm text-[var(--text-muted)]">No previous versions yet.</p>
                @endforelse
            </div>
        </aside>
    </div>

    @if($showSettingsPanel)
        <div class="dt-settings-backdrop" wire:click="$set('showSettingsPanel', false)"></div>
        <div class="dt-settings-panel">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-base font-semibold">Page, branding & numbering</h2>
                <button type="button" wire:click="$set('showSettingsPanel', false)" class="s-btn s-btn-ghost s-btn-sm">Close</button>
            </div>

            <form wire:submit="saveSettings" class="space-y-6">
                <x-signals.form-section title="Page layout">
                    <div class="grid grid-cols-2 gap-3">
                        <flux:input wire:model="pageConfig.page_size" label="Page size" />
                        <flux:input wire:model="pageConfig.page_orientation" label="Orientation" />
                        <flux:input wire:model="pageConfig.margins.top" label="Margin top (mm)" type="number" />
                        <flux:input wire:model="pageConfig.margins.right" label="Margin right (mm)" type="number" />
                        <flux:input wire:model="pageConfig.margins.bottom" label="Margin bottom (mm)" type="number" />
                        <flux:input wire:model="pageConfig.margins.left" label="Margin left (mm)" type="number" />
                    </div>
                </x-signals.form-section>

                <x-signals.form-section title="Branding overrides">
                    <div class="space-y-3">
                        <flux:input wire:model="brandingConfig.primary_colour" label="Primary colour" />
                        <flux:input wire:model="brandingConfig.secondary_colour" label="Secondary colour" />
                        <flux:input wire:model="brandingConfig.font_family" label="Font family" />
                        <div>
                            <label class="block text-sm font-medium mb-1">Logo</label>
                            <input type="file" wire:model="logoUpload" accept="image/png,image/jpeg,image/svg+xml,image/webp" class="text-sm">
                            @if(! empty($brandingConfig['logo_path']))
                                <p class="text-xs text-[var(--text-muted)] mt-1">Current: {{ $brandingConfig['logo_path'] }}</p>
                            @endif
                        </div>
                    </div>
                </x-signals.form-section>

                @if($sequence)
                    <x-signals.form-section title="Document numbering">
                        <div class="grid grid-cols-2 gap-3">
                            <flux:input wire:model="sequencePrefix" label="Prefix" />
                            <flux:input wire:model="sequenceSuffix" label="Suffix" />
                            <flux:input wire:model="sequenceNextNumber" label="Next number" type="number" min="1" />
                            <flux:input wire:model="sequencePadding" label="Padding" type="number" min="1" max="12" />
                            <flux:switch wire:model="sequenceIncludeYear" label="Include year" />
                            <flux:switch wire:model="sequenceIncludeMonth" label="Include month" />
                        </div>
                    </x-signals.form-section>
                @endif

                <flux:button variant="primary" type="submit">Save settings</flux:button>
            </form>
        </div>
    @endif
</div>
