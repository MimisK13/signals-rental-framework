@can('create', \App\Models\DocumentTemplate::class)
    <x-signals.modal wire:key="document-template-create-modal" name="new-document-template" title="New template" size="md" :teleport="false">
        <div class="space-y-4">
            <div class="space-y-2">
                <p class="text-sm font-medium text-[var(--text-primary)]">Starting point</p>
                <div class="space-y-2">
                    <label class="flex items-start gap-3 rounded border border-[var(--card-border)] p-3 cursor-pointer">
                        <input type="radio" wire:model.live="createStartingPoint" value="duplicate" class="mt-1" />
                        <span>
                            <span class="block text-sm font-medium text-[var(--text-primary)]">Base on an existing template</span>
                            <span class="block text-xs text-[var(--text-secondary)]">Copy content from a template in this store.</span>
                        </span>
                    </label>
                    <label class="flex items-start gap-3 rounded border border-[var(--card-border)] p-3 cursor-pointer">
                        <input type="radio" wire:model.live="createStartingPoint" value="scaffold" class="mt-1" />
                        <span>
                            <span class="block text-sm font-medium text-[var(--text-primary)]">Blank for a document type</span>
                            <span class="block text-xs text-[var(--text-secondary)]">Start from the system scaffold for the selected type.</span>
                        </span>
                    </label>
                    <label class="flex items-start gap-3 rounded border border-[var(--card-border)] p-3 cursor-pointer">
                        <input type="radio" wire:model.live="createStartingPoint" value="empty" class="mt-1" />
                        <span>
                            <span class="block text-sm font-medium text-[var(--text-primary)]">Completely empty</span>
                            <span class="block text-xs text-[var(--text-secondary)]">Create a template with no body, header, footer, or styles.</span>
                        </span>
                    </label>
                </div>
            </div>

            <flux:input wire:model="createName" label="Name" placeholder="e.g. Branded quote" />

            @if($stores->count() > 1)
                <flux:select wire:model.live="createStoreId" label="Store">
                    <flux:select.option value="">Select a store</flux:select.option>
                    @foreach($stores as $store)
                        <flux:select.option value="{{ $store->id }}">{{ $store->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            @if($createStartingPoint === 'duplicate')
                <flux:select wire:model="createSourceTemplateId" label="Source template">
                    <flux:select.option value="">Select a template</flux:select.option>
                    @foreach($sourceTemplates as $template)
                        <flux:select.option value="{{ $template->id }}">{{ $template->name }} ({{ str($template->document_type)->headline()->replace('_', ' ') }})</flux:select.option>
                    @endforeach
                </flux:select>
            @else
                <flux:select wire:model="createDocumentType" label="Document type">
                    @foreach($documentTypeOptions as $value => $label)
                        <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif
        </div>

        <x-slot:footer>
            <button type="button" class="s-btn s-btn-sm" x-on:click="$dispatch('close-modal', 'new-document-template')">Cancel</button>
            <button
                type="button"
                class="s-btn s-btn-sm s-btn-primary"
                wire:click="createTemplate"
                wire:loading.attr="disabled"
                wire:target="createTemplate"
            >
                <span wire:loading.remove wire:target="createTemplate">Create template</span>
                <span wire:loading wire:target="createTemplate" class="inline-flex items-center gap-1.5">
                    <x-signals.spinner size="xs" /> Creating…
                </span>
            </button>
        </x-slot:footer>
    </x-signals.modal>
@endcan
