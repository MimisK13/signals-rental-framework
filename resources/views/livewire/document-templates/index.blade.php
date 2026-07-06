<?php

use App\Actions\Documents\CreateDocumentTemplate;
use App\Actions\Documents\DeleteDocumentTemplate;
use App\Actions\Documents\DuplicateDocumentTemplate;
use App\Actions\Documents\RestoreDefaultDocumentTemplate;
use App\Actions\Documents\SetDefaultDocumentTemplate;
use App\Actions\Documents\UpdateDocumentTemplate;
use App\Data\Documents\CreateDocumentTemplateData;
use App\Data\Documents\UpdateDocumentTemplateData;
use App\Models\DocumentTemplate;
use App\Models\Store;
use App\Services\Documents\SystemDocumentTemplates;
use App\Views\DocumentTemplateColumnRegistry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Document Templates')] class extends Component {
    /** @var Collection<int, Store> */
    public Collection $stores;

    #[Url(as: 'store')]
    public string $storeFilter = '';

    public string $createStartingPoint = 'scaffold';

    public string $createName = '';

    public string $createStoreId = '';

    public string $createSourceTemplateId = '';

    public string $createDocumentType = 'quote';

    public function mount(): void
    {
        Gate::authorize('viewAny', DocumentTemplate::class);
        $this->loadStores();
        $this->resetCreateForm();
    }

    public function loadStores(): void
    {
        $storeQuery = Store::query()->orderBy('name');
        $storeIds = auth()->user()?->accessibleStoreIds();

        if ($storeIds !== null) {
            $storeQuery->whereIn('id', $storeIds);
        }

        $this->stores = $storeQuery->get();
    }

    public function updatedStoreFilter(): void
    {
        $this->resetCreateForm();
    }

    public function resetCreateForm(): void
    {
        $this->createStartingPoint = 'scaffold';
        $this->createName = '';
        $this->createSourceTemplateId = '';
        $this->createDocumentType = 'quote';
        $this->createStoreId = $this->defaultCreateStoreId();
    }

    public function createTemplate(): void
    {
        Gate::authorize('create', DocumentTemplate::class);

        $rules = [
            'createName' => ['required', 'string', 'max:255'],
            'createStoreId' => ['required', 'integer', 'exists:stores,id'],
            'createStartingPoint' => ['required', Rule::in(['duplicate', 'scaffold', 'empty'])],
        ];

        if ($this->createStartingPoint === 'duplicate') {
            $rules['createSourceTemplateId'] = [
                'required',
                'integer',
                Rule::exists('document_templates', 'id')->where(fn ($query) => $query->where('store_id', (int) $this->createStoreId)),
            ];
        } else {
            $rules['createDocumentType'] = ['required', 'string', Rule::in(SystemDocumentTemplates::CORE_TYPES)];
        }

        $this->validate($rules);

        $storeId = (int) $this->createStoreId;
        $templateId = match ($this->createStartingPoint) {
            'duplicate' => $this->createFromExistingTemplate($storeId),
            'scaffold' => $this->createFromScaffold($storeId),
            'empty' => $this->createEmptyTemplate($storeId),
        };

        $this->redirect(route('admin.document-templates.edit', $templateId));
    }

    private function createFromExistingTemplate(int $storeId): int
    {
        $source = DocumentTemplate::query()
            ->where('store_id', $storeId)
            ->findOrFail((int) $this->createSourceTemplateId);

        $result = (new DuplicateDocumentTemplate)($source);
        $template = DocumentTemplate::query()->findOrFail($result->id);

        (new UpdateDocumentTemplate)($template, UpdateDocumentTemplateData::from([
            'name' => $this->createName,
            'slug' => $this->uniqueSlug($storeId, Str::slug($this->createName)),
        ]));

        return $template->id;
    }

    private function createFromScaffold(int $storeId): int
    {
        $definition = SystemDocumentTemplates::definition($this->createDocumentType);

        $result = (new CreateDocumentTemplate)(CreateDocumentTemplateData::from([
            'store_id' => $storeId,
            'name' => $this->createName,
            'slug' => $this->uniqueSlug($storeId, Str::slug($this->createName)),
            'description' => null,
            'document_type' => $definition['document_type'],
            'model_type' => $definition['model_type'],
            'body_html' => $definition['body_html'],
            'header_html' => $definition['header_html'],
            'footer_html' => $definition['footer_html'],
            'styles_css' => $definition['styles_css'],
            'page_config' => $definition['page_config'],
        ]));

        return $result->id;
    }

    private function createEmptyTemplate(int $storeId): int
    {
        $result = (new CreateDocumentTemplate)(CreateDocumentTemplateData::from([
            'store_id' => $storeId,
            'name' => $this->createName,
            'slug' => $this->uniqueSlug($storeId, Str::slug($this->createName)),
            'description' => null,
            'document_type' => $this->createDocumentType,
            'body_html' => '',
            'header_html' => '',
            'footer_html' => '',
            'styles_css' => '',
        ]));

        return $result->id;
    }

    private function uniqueSlug(int $storeId, string $baseSlug): string
    {
        $slug = $baseSlug !== '' ? $baseSlug : 'template';
        $suffix = 2;

        while (DocumentTemplate::query()->where('store_id', $storeId)->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    private function defaultCreateStoreId(): string
    {
        if ($this->storeFilter !== '') {
            return $this->storeFilter;
        }

        if ($this->stores->count() === 1) {
            return (string) $this->stores->first()->id;
        }

        return '';
    }

    public function duplicateTemplate(int $templateId): void
    {
        $template = DocumentTemplate::query()->findOrFail($templateId);
        (new DuplicateDocumentTemplate)($template);
    }

    public function setDefault(int $templateId): void
    {
        $template = DocumentTemplate::query()->findOrFail($templateId);
        (new SetDefaultDocumentTemplate)($template);
    }

    public function restoreDefault(int $templateId): void
    {
        $template = DocumentTemplate::query()->findOrFail($templateId);
        (new RestoreDefaultDocumentTemplate)($template);
    }

    public function deleteTemplate(int $templateId): void
    {
        $template = DocumentTemplate::query()->findOrFail($templateId);
        (new DeleteDocumentTemplate)($template);
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $registry = app(DocumentTemplateColumnRegistry::class);
        $columnMeta = fn (string $key): array => [
            'sortable' => $registry->get($key)?->sortable ?? false,
            'filterable' => $registry->get($key)?->filterable ?? false,
        ];

        $typeOptions = DocumentTemplate::query()
            ->select('document_type')
            ->distinct()
            ->orderBy('document_type')
            ->pluck('document_type')
            ->mapWithKeys(fn (string $type): array => [$type => str($type)->headline()->replace('_', ' ')->toString()])
            ->all();

        $createStoreId = $this->createStoreId !== '' ? (int) $this->createStoreId : null;

        $sourceTemplateQuery = DocumentTemplate::query()
            ->where('is_active', true)
            ->orderBy('document_type')
            ->orderBy('name');

        if ($createStoreId !== null) {
            $sourceTemplateQuery->where('store_id', $createStoreId);
        } elseif ($this->stores->isNotEmpty()) {
            $sourceTemplateQuery->whereIn('store_id', $this->stores->pluck('id'));
        }

        $documentTypeOptions = collect(SystemDocumentTemplates::CORE_TYPES)
            ->mapWithKeys(fn (string $type): array => [$type => str($type)->headline()->replace('_', ' ')->toString()])
            ->all();

        return [
            'columns' => [
                ['key' => 'name', 'label' => 'Name', ...$columnMeta('name'), 'filter_type' => 'text', 'view' => 'livewire.document-templates.partials.column-name'],
                ['key' => 'document_type', 'label' => 'Document Type', ...$columnMeta('document_type'), 'filter_type' => 'select', 'filter_options' => $typeOptions, 'view' => 'livewire.document-templates.partials.column-document-type'],
                ['key' => 'store_id', 'label' => 'Store', ...$columnMeta('store_id'), 'view' => 'livewire.document-templates.partials.column-store'],
                ['key' => 'is_active', 'label' => 'Status', 'view' => 'livewire.document-templates.partials.column-status-badges'],
                ['key' => 'version', 'label' => 'Version', ...$columnMeta('version'), 'view' => 'livewire.document-templates.partials.column-version'],
                ['key' => 'last_edited_at', 'label' => 'Last Edited', ...$columnMeta('last_edited_at'), 'view' => 'livewire.document-templates.partials.column-last-edited'],
                ['key' => 'actions', 'type' => 'actions'],
            ],
            'scopes' => $this->storeFilter !== '' ? ['forStore' => (int) $this->storeFilter] : [],
            'sourceTemplates' => $sourceTemplateQuery->get(),
            'documentTypeOptions' => $documentTypeOptions,
        ];
    }
}; ?>

<section class="w-full">
    <x-admin.layout group="documents" title="Document Templates" description="Manage PDF and print templates with saved views, filters, and column layouts.">
        @if($stores->count() > 1)
            <div class="mb-4 flex flex-wrap items-center gap-3">
                <flux:select wire:model.live="storeFilter" label="Store" class="max-w-xs">
                    <flux:select.option value="">All stores</flux:select.option>
                    @foreach($stores as $store)
                        <flux:select.option value="{{ $store->id }}">{{ $store->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        @endif

        <livewire:components.data-table
            :columns="$columns"
            :model="\App\Models\DocumentTemplate::class"
            :searchable="['name', 'slug']"
            :with="['store']"
            :scopes="$scopes"
            default-sort="document_type"
            empty-message="No document templates found."
            actions-view="livewire.document-templates.partials.row-actions"
            toolbar-view="livewire.document-templates.partials.toolbar"
            entity-type="document_templates"
            :key="'document-templates-table-' . $storeFilter"
        />
    </x-admin.layout>

    @include('livewire.document-templates.partials.create-modal')
</section>
