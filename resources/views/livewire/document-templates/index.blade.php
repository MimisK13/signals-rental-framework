<?php

use App\Actions\Documents\DeleteDocumentTemplate;
use App\Actions\Documents\DuplicateDocumentTemplate;
use App\Actions\Documents\RestoreDefaultDocumentTemplate;
use App\Actions\Documents\SetDefaultDocumentTemplate;
use App\Models\DocumentTemplate;
use App\Models\Store;
use App\Services\Documents\SystemDocumentTemplates;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
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

    #[Computed]
    public function groupedTemplates(): Collection
    {
        $query = DocumentTemplate::query()
            ->with('store')
            ->orderBy('document_type')
            ->orderByDesc('is_default')
            ->orderBy('name');

        $storeIds = auth()->user()?->accessibleStoreIds();

        if ($storeIds !== null) {
            $query->whereIn('store_id', $storeIds);
        }

        if ($this->storeFilter !== '') {
            $query->where('store_id', (int) $this->storeFilter);
        }

        return $query->get()->groupBy('document_type');
    }

    public function updatedStoreFilter(): void
    {
        unset($this->groupedTemplates);
    }

    public function duplicateTemplate(int $templateId): void
    {
        $template = DocumentTemplate::query()->findOrFail($templateId);
        (new DuplicateDocumentTemplate)($template);
        unset($this->groupedTemplates);
    }

    public function setDefault(int $templateId): void
    {
        $template = DocumentTemplate::query()->findOrFail($templateId);
        (new SetDefaultDocumentTemplate)($template);
        unset($this->groupedTemplates);
    }

    public function restoreDefault(int $templateId): void
    {
        $template = DocumentTemplate::query()->findOrFail($templateId);
        (new RestoreDefaultDocumentTemplate)($template);
        unset($this->groupedTemplates);
    }

    public function deleteTemplate(int $templateId): void
    {
        $template = DocumentTemplate::query()->findOrFail($templateId);
        (new DeleteDocumentTemplate)($template);
        unset($this->groupedTemplates);
    }

    public function documentTypeLabel(string $type): string
    {
        return str($type)->headline()->replace('_', ' ')->toString();
    }
}; ?>

<section class="w-full">
    <x-admin.layout group="documents" title="Document Templates" description="Manage PDF and print templates grouped by document type.">
        <div class="mb-4 flex flex-wrap items-center gap-3">
            @if($stores->count() > 1)
                <flux:select wire:model.live="storeFilter" label="Store" class="max-w-xs">
                    <flux:select.option value="">All stores</flux:select.option>
                    @foreach($stores as $store)
                        <flux:select.option value="{{ $store->id }}">{{ $store->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif
        </div>

        @forelse($this->groupedTemplates as $documentType => $templates)
            <x-signals.card class="mb-6" :title="$this->documentTypeLabel($documentType)">
                <p class="mb-4 text-sm text-[var(--text-muted)]">{{ $templates->count() }} template{{ $templates->count() === 1 ? '' : 's' }}</p>

                <div class="s-table-wrap">
                    <table class="s-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Store</th>
                                <th>Status</th>
                                <th>Version</th>
                                <th>Last edited</th>
                                <th class="w-[220px]"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($templates as $template)
                                <tr wire:key="document-template-{{ $template->id }}">
                                    <td class="font-medium">{{ $template->name }}</td>
                                    <td>{{ $template->store?->name }}</td>
                                    <td>
                                        <div class="flex flex-wrap gap-1">
                                            @if($template->is_default)
                                                <span class="s-badge s-badge-green">Default</span>
                                            @endif
                                            @if($template->is_system)
                                                <span class="s-badge">System</span>
                                            @endif
                                            @if($template->is_active)
                                                <span class="s-badge s-badge-blue">Active</span>
                                            @else
                                                <span class="s-badge">Inactive</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td>v{{ $template->version }}</td>
                                    <td>{{ $template->last_edited_at?->diffForHumans() ?? '—' }}</td>
                                    <td class="text-right">
                                        <div class="flex flex-wrap justify-end gap-1">
                                            @can('update', $template)
                                                <a href="{{ route('admin.document-templates.edit', $template) }}" wire:navigate class="s-btn s-btn-ghost s-btn-sm">Edit</a>
                                                <button type="button" wire:click="duplicateTemplate({{ $template->id }})" class="s-btn s-btn-ghost s-btn-sm">Duplicate</button>
                                                @if(! $template->is_default)
                                                    <button type="button" wire:click="setDefault({{ $template->id }})" class="s-btn s-btn-ghost s-btn-sm">Set default</button>
                                                @endif
                                                @if($template->is_system)
                                                    <button type="button"
                                                            wire:click="restoreDefault({{ $template->id }})"
                                                            wire:confirm="Restore this system template to its default content?"
                                                            class="s-btn s-btn-ghost s-btn-sm">
                                                        Restore default
                                                    </button>
                                                @elseif(! $template->is_system)
                                                    <button type="button"
                                                            wire:click="deleteTemplate({{ $template->id }})"
                                                            wire:confirm="Delete this template?"
                                                            class="s-btn-ghost s-btn-xs text-[var(--red)]">
                                                        Delete
                                                    </button>
                                                @endif
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-signals.card>
        @empty
            <x-signals.panel>
                <p class="text-center text-[var(--text-muted)]">No document templates found.</p>
            </x-signals.panel>
        @endforelse
    </x-admin.layout>
</section>
