<?php

use App\Actions\Documents\UpdateDocumentSequence;
use App\Data\Documents\UpdateDocumentSequenceData;
use App\Models\DocumentSequence;
use App\Models\Store;
use App\Services\Documents\DocumentNumberGenerator;
use App\Services\Documents\DocumentSequenceDefaults;
use App\Services\Documents\EntityNumberSchemeCatalog;
use App\Support\NumberingAccess;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Numbering')] class extends Component {
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
        $this->formPrefix = (string) ($attributes['prefix'] ?? '');
        $this->formSuffix = (string) ($attributes['suffix'] ?? '');
        $this->formIncludeYear = (bool) ($attributes['include_year'] ?? false);
        $this->formIncludeMonth = (bool) ($attributes['include_month'] ?? false);
        $this->formNextNumber = (int) ($attributes['next_number'] ?? 1);
        $this->formPadding = (int) ($attributes['padding'] ?? 4);
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
            (string) ($attributes['prefix'] ?? ''),
            (string) ($attributes['suffix'] ?? ''),
            (bool) ($attributes['include_year'] ?? false),
            (bool) ($attributes['include_month'] ?? false),
            (int) ($attributes['next_number'] ?? 1),
            (int) ($attributes['padding'] ?? 4),
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
}; ?>

<section class="w-full">
    <x-admin.layout group="documents" title="Numbering" description="Configure entity number formats for invoices, orders, members, and payments.">
        @foreach($stores as $store)
            <x-signals.form-section :title="$store->name" wire:key="numbering-store-{{ $store->id }}">
                <div class="s-table-wrap">
                    <table class="s-table">
                        <thead>
                            <tr>
                                <th>Entity</th>
                                <th>Next number preview</th>
                                <th class="w-[100px]"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($this->storeScopedRows as $row)
                                @continue($row['store_id'] !== $store->id)
                                <tr wire:key="numbering-row-{{ $row['store_id'] }}-{{ $row['document_type'] }}">
                                    <td class="font-medium">{{ $row['label'] }}</td>
                                    <td><code class="text-sm">{{ $row['preview'] }}</code></td>
                                    <td class="text-right">
                                        @can('update', \App\Support\NumberingAccess::class)
                                            <button
                                                type="button"
                                                wire:click="openEdit({{ $row['store_id'] }}, @js($row['document_type']))"
                                                class="s-btn s-btn-ghost s-btn-sm"
                                                title="Edit"
                                            >
                                                <flux:icon.pencil-square class="w-4 h-4" />
                                            </button>
                                        @endcan
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-signals.form-section>
        @endforeach

        <x-signals.form-section title="Global">
            <flux:text class="mb-4 text-sm text-zinc-500 dark:text-zinc-400">
                Member and payment numbers are shared across all stores.
            </flux:text>
            <div class="s-table-wrap">
                <table class="s-table">
                    <thead>
                        <tr>
                            <th>Entity</th>
                            <th>Next number preview</th>
                            <th class="w-[100px]"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($this->globalRows as $row)
                            <tr wire:key="numbering-global-{{ $row['document_type'] }}">
                                <td class="font-medium">{{ $row['label'] }}</td>
                                <td><code class="text-sm">{{ $row['preview'] }}</code></td>
                                <td class="text-right">
                                    @can('update', \App\Support\NumberingAccess::class)
                                        <button
                                            type="button"
                                            wire:click="openEdit({{ $row['store_id'] }}, @js($row['document_type']))"
                                            class="s-btn s-btn-ghost s-btn-sm"
                                            title="Edit"
                                        >
                                            <flux:icon.pencil-square class="w-4 h-4" />
                                        </button>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-signals.form-section>
    </x-admin.layout>

    <flux:modal wire:model="showEditModal" class="max-w-lg">
        @if($editingDocumentType)
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">
                        Edit {{ EntityNumberSchemeCatalog::labelFor($editingDocumentType) }}
                    </flux:heading>
                    @if($editingStoreId && ! EntityNumberSchemeCatalog::isGlobalType($editingDocumentType))
                        <flux:text class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                            {{ $stores->firstWhere('id', $editingStoreId)?->name }}
                        </flux:text>
                    @endif
                </div>

                <div class="rounded-lg border border-zinc-200 bg-zinc-50 px-4 py-3 dark:border-zinc-700 dark:bg-zinc-900">
                    <flux:text class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Live preview</flux:text>
                    <div class="mt-1 font-mono text-lg">{{ $this->formPreview }}</div>
                </div>

                <form wire:submit="saveSequence" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <flux:input wire:model.live="formPrefix" label="Prefix" />
                        <flux:input wire:model.live="formSuffix" label="Suffix" />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <flux:input wire:model.live="formNextNumber" label="Next number" type="number" min="1" />
                        <flux:input wire:model.live="formPadding" label="Digits" type="number" min="1" max="12" />
                    </div>
                    <div class="flex flex-col gap-3">
                        <flux:switch wire:model.live="formIncludeYear" label="Include year" />
                        <flux:switch wire:model.live="formIncludeMonth" label="Include month" />
                    </div>

                    @error('formNextNumber')
                        <flux:text class="text-sm text-red-600 dark:text-red-400">{{ $message }}</flux:text>
                    @enderror
                    @error('formPadding')
                        <flux:text class="text-sm text-red-600 dark:text-red-400">{{ $message }}</flux:text>
                    @enderror

                    <div class="flex items-center justify-end gap-3">
                        <flux:button type="button" variant="ghost" wire:click="closeEdit">Cancel</flux:button>
                        <flux:button type="submit" variant="primary">Save</flux:button>
                    </div>
                </form>
            </div>
        @endif
    </flux:modal>
</section>
