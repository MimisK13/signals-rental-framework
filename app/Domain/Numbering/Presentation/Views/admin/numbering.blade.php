<section class="w-full min-w-0">
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
                                        @can('update', \App\Domain\Numbering\Support\NumberingAccess::class)
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
                                    @can('update', \App\Domain\Numbering\Support\NumberingAccess::class)
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
                        Edit {{ \App\Domain\Numbering\Services\EntityNumberSchemeCatalog::labelFor($editingDocumentType) }}
                    </flux:heading>
                    @if($editingStoreId && ! \App\Domain\Numbering\Services\EntityNumberSchemeCatalog::isGlobalType($editingDocumentType))
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
