<?php

declare(strict_types=1);

namespace App\Domain\Documents\Presentation\Livewire;

use App\Domain\Documents\Actions\CreateDocumentTemplate;
use App\Domain\Documents\Actions\DeleteDocumentTemplate;
use App\Domain\Documents\Actions\DuplicateDocumentTemplate;
use App\Domain\Documents\Actions\RestoreDefaultDocumentTemplate;
use App\Domain\Documents\Actions\SetDefaultDocumentTemplate;
use App\Domain\Documents\Actions\UpdateDocumentTemplate;
use App\Domain\Documents\Data\CreateDocumentTemplateData;
use App\Domain\Documents\Data\UpdateDocumentTemplateData;
use App\Domain\Documents\Models\DocumentTemplate;
use App\Domain\Documents\Presentation\Tables\DocumentTemplateColumnRegistry;
use App\Domain\Documents\Services\SystemDocumentTemplates;
use App\Domain\Stores\Models\Store;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Document Templates')]
final class Index extends Component
{
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
            default => throw new \LogicException('Unsupported document template starting point.'),
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
            'sortable' => $registry->get($key)->sortable,
            'filterable' => $registry->get($key)->filterable,
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
                ['key' => 'name', 'label' => 'Name', ...$columnMeta('name'), 'filter_type' => 'text', 'view' => 'documents::templates.partials.column-name'],
                ['key' => 'document_type', 'label' => 'Document Type', ...$columnMeta('document_type'), 'filter_type' => 'select', 'filter_options' => $typeOptions, 'view' => 'documents::templates.partials.column-document-type'],
                ['key' => 'store_id', 'label' => 'Store', ...$columnMeta('store_id'), 'view' => 'documents::templates.partials.column-store'],
                ['key' => 'is_active', 'label' => 'Status', 'view' => 'documents::templates.partials.column-status-badges'],
                ['key' => 'version', 'label' => 'Version', ...$columnMeta('version'), 'view' => 'documents::templates.partials.column-version'],
                ['key' => 'last_edited_at', 'label' => 'Last Edited', ...$columnMeta('last_edited_at'), 'view' => 'documents::templates.partials.column-last-edited'],
                ['key' => 'actions', 'type' => 'actions'],
            ],
            'scopes' => $this->storeFilter !== '' ? ['forStore' => (int) $this->storeFilter] : [],
            'sourceTemplates' => $sourceTemplateQuery->get(),
            'documentTypeOptions' => $documentTypeOptions,
        ];
    }

    public function render(): View
    {
        return view('documents::templates.index', $this->with());
    }
}
