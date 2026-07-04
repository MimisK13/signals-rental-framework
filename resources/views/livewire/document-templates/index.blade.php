<?php

use App\Actions\Documents\DeleteDocumentTemplate;
use App\Actions\Documents\DuplicateDocumentTemplate;
use App\Actions\Documents\RestoreDefaultDocumentTemplate;
use App\Actions\Documents\SetDefaultDocumentTemplate;
use App\Models\DocumentTemplate;
use App\Models\Store;
use App\Views\DocumentTemplateColumnRegistry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Document Templates')] class extends Component {
    /** @var Collection<int, Store> */
    public Collection $stores;

    #[Url(as: 'store')]
    public string $storeFilter = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', DocumentTemplate::class);
        $this->loadStores();
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
</section>
