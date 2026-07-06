<x-signals.column-toggle />
<x-signals.export-button />
@can('create', \App\Models\DocumentTemplate::class)
    <button type="button" class="s-btn s-btn-sm s-btn-accent" x-on:click="$dispatch('open-modal', 'new-document-template')">
        <flux:icon.plus class="!size-4" />
        New template
    </button>
@endcan
