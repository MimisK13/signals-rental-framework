<?php

declare(strict_types=1);

namespace App\Domain\Numbering\Presentation\Livewire\Admin;

use App\Domain\Numbering\Actions\UpdateDocumentSequence;
use App\Domain\Numbering\Data\UpdateDocumentSequenceData;
use App\Domain\Numbering\Models\DocumentSequence;
use App\Domain\Numbering\Services\DocumentNumberGenerator;
use App\Domain\Numbering\Services\DocumentSequenceDefaults;
use App\Domain\Numbering\Services\EntityNumberSchemeCatalog;
use App\Domain\Numbering\Support\NumberingAccess;
use App\Domain\Stores\Models\Store;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Numbering')]
final class Numbering extends Component
{
    /** @var Collection<int, Store> */
    public Collection $stores;

    public int $defaultStoreId = 0;

    public bool $showEditModal = false;

    public ?int $editingStoreId = null;

    public ?string $editingDocumentType = null;

    public string $formPrefix = '';

    public string $formSuffix = '';

    public bool $formIncludeYear = false;

    public bool $formIncludeMonth = false;

    public int $formNextNumber = 1;

    public int $formPadding = 4;

    public function mount(): void
    {
        Gate::authorize('viewAny', NumberingAccess::class);

        $this->stores = Store::query()->orderBy('name')->get();
        $this->defaultStoreId = $this->resolveDefaultStoreId();
    }

    /**
     * @return list<array{store_id: int, store_name: string, document_type: string, label: string, preview: string}>
     */
    #[Computed]
    public function storeScopedRows(): array
    {
        $generator = app(DocumentNumberGenerator::class);
        $rows = [];

        foreach ($this->stores as $store) {
            foreach (EntityNumberSchemeCatalog::STORE_SCOPED_TYPES as $documentType) {
                $rows[] = $this->buildRow($store, $documentType, $generator);
            }
        }

        return $rows;
    }

    /**
     * @return list<array{store_id: int, store_name: string, document_type: string, label: string, preview: string}>
     */
    #[Computed]
    public function globalRows(): array
    {
        $store = $this->stores->firstWhere('id', $this->defaultStoreId)
            ?? $this->stores->first();

        if ($store === null) {
            return [];
        }

        $generator = app(DocumentNumberGenerator::class);
        $rows = [];

        foreach (EntityNumberSchemeCatalog::GLOBAL_TYPES as $documentType) {
            $rows[] = $this->buildRow($store, $documentType, $generator);
        }

        return $rows;
    }

    #[Computed]
    public function formPreview(): string
    {
        if ($this->editingStoreId === null || $this->editingDocumentType === null) {
            return '';
        }

        $store = $this->stores->firstWhere('id', $this->editingStoreId);

        if ($store === null) {
            return '';
        }

        return $this->previewFromAttributes(
            $store,
            $this->formPrefix,
            $this->formSuffix,
            $this->formIncludeYear,
            $this->formIncludeMonth,
            $this->formNextNumber,
            $this->formPadding,
        );
    }

    public function openEdit(int $storeId, string $documentType): void
    {
        Gate::authorize('viewAny', NumberingAccess::class);

        $attributes = $this->resolveSequenceAttributes($storeId, $documentType);

        $this->editingStoreId = $storeId;
        $this->editingDocumentType = $documentType;
        $this->formPrefix = $attributes['prefix'];
        $this->formSuffix = $attributes['suffix'];
        $this->formIncludeYear = $attributes['include_year'];
        $this->formIncludeMonth = $attributes['include_month'];
        $this->formNextNumber = $attributes['next_number'];
        $this->formPadding = $attributes['padding'];
        $this->resetErrorBag();
        $this->showEditModal = true;
    }

    public function closeEdit(): void
    {
        $this->showEditModal = false;
        $this->editingStoreId = null;
        $this->editingDocumentType = null;
        $this->resetErrorBag();
    }

    public function saveSequence(): void
    {
        Gate::authorize('update', NumberingAccess::class);

        if ($this->editingStoreId === null || $this->editingDocumentType === null) {
            return;
        }

        $validated = $this->validate([
            'formPrefix' => ['nullable', 'string', 'max:50'],
            'formSuffix' => ['nullable', 'string', 'max:50'],
            'formIncludeYear' => ['boolean'],
            'formIncludeMonth' => ['boolean'],
            'formNextNumber' => ['required', 'integer', 'min:1'],
            'formPadding' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        try {
            UpdateDocumentSequenceData::assertNextNumberFitsPadding(
                $validated['formNextNumber'],
                $validated['formPadding'],
            );
        } catch (ValidationException $exception) {
            $this->addError('formNextNumber', $exception->validator->errors()->first('next_number'));

            return;
        }

        $sequence = DocumentSequenceDefaults::ensureExists($this->editingStoreId, $this->editingDocumentType);

        try {
            (new UpdateDocumentSequence)($sequence, new UpdateDocumentSequenceData(
                prefix: $validated['formPrefix'],
                suffix: $validated['formSuffix'] !== '' ? $validated['formSuffix'] : null,
                include_year: $validated['formIncludeYear'],
                include_month: $validated['formIncludeMonth'],
                next_number: $validated['formNextNumber'],
                padding: $validated['formPadding'],
            ));
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                $mappedField = match ($field) {
                    'next_number' => 'formNextNumber',
                    'padding' => 'formPadding',
                    'prefix' => 'formPrefix',
                    'suffix' => 'formSuffix',
                    default => 'formNextNumber',
                };

                $this->addError($mappedField, $messages[0] ?? 'Validation failed.');
            }

            return;
        }

        unset($this->storeScopedRows, $this->globalRows);
        $this->closeEdit();
        $this->dispatch('numbering-scheme-saved');
    }

    /**
     * @return array{prefix: string, suffix: string, include_year: bool, include_month: bool, next_number: int, padding: int}
     */
    private function resolveSequenceAttributes(int $storeId, string $documentType): array
    {
        $sequence = DocumentSequence::query()
            ->where('store_id', $storeId)
            ->where('document_type', $documentType)
            ->first();

        if ($sequence !== null) {
            return [
                'prefix' => (string) ($sequence->prefix ?? ''),
                'suffix' => (string) ($sequence->suffix ?? ''),
                'include_year' => (bool) $sequence->include_year,
                'include_month' => (bool) $sequence->include_month,
                'next_number' => (int) $sequence->next_number,
                'padding' => (int) $sequence->padding,
            ];
        }

        return DocumentSequenceDefaults::attributes($documentType);
    }

    /**
     * @return array{store_id: int, store_name: string, document_type: string, label: string, preview: string}
     */
    private function buildRow(Store $store, string $documentType, DocumentNumberGenerator $generator): array
    {
        $attributes = $this->resolveSequenceAttributes((int) $store->id, $documentType);

        $preview = $this->previewFromAttributes(
            $store,
            $attributes['prefix'],
            $attributes['suffix'],
            $attributes['include_year'],
            $attributes['include_month'],
            $attributes['next_number'],
            $attributes['padding'],
        );

        return [
            'store_id' => (int) $store->id,
            'store_name' => (string) $store->name,
            'document_type' => $documentType,
            'label' => EntityNumberSchemeCatalog::labelFor($documentType),
            'preview' => $preview,
        ];
    }

    private function previewFromAttributes(
        Store $store,
        string $prefix,
        string $suffix,
        bool $includeYear,
        bool $includeMonth,
        int $nextNumber,
        int $padding,
    ): string {
        $sequence = new DocumentSequence([
            'prefix' => $prefix,
            'suffix' => $suffix,
            'include_year' => $includeYear,
            'include_month' => $includeMonth,
            'padding' => max(1, min(12, $padding)),
            'next_number' => max(1, $nextNumber),
        ]);
        $sequence->setRelation('store', $store);

        return app(DocumentNumberGenerator::class)->formatNumber($sequence, max(1, $nextNumber));
    }

    private function resolveDefaultStoreId(): int
    {
        $defaultStoreId = Store::query()->where('is_default', true)->value('id');

        if (is_int($defaultStoreId)) {
            return $defaultStoreId;
        }

        return (int) (Store::query()->orderBy('id')->value('id') ?? 0);
    }

    public function render(): View
    {
        return view('numbering::admin.numbering');
    }
}
