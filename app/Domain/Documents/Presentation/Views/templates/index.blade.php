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
            :model="\App\Domain\Documents\Models\DocumentTemplate::class"
            :searchable="['name', 'slug']"
            :with="['store']"
            :scopes="$scopes"
            default-sort="document_type"
            empty-message="No document templates found."
            actions-view="documents::templates.partials.row-actions"
            toolbar-view="documents::templates.partials.toolbar"
            entity-type="document_templates"
            :key="'document-templates-table-' . $storeFilter"
        />
    </x-admin.layout>

    @include('documents::templates.partials.create-modal')
</section>
