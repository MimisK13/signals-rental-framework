<div class="flex flex-wrap justify-end gap-1">
    @can('update', $item)
        <a href="{{ route('admin.document-templates.edit', $item) }}" wire:navigate class="s-btn s-btn-ghost s-btn-sm">Edit</a>
        <button type="button" wire:click="$parent.duplicateTemplate({{ $item->id }})" class="s-btn s-btn-ghost s-btn-sm">Duplicate</button>
        @if(! $item->is_default)
            <button type="button" wire:click="$parent.setDefault({{ $item->id }})" class="s-btn s-btn-ghost s-btn-sm">Set default</button>
        @endif
        @if($item->is_system)
            <button type="button"
                    wire:click="$parent.restoreDefault({{ $item->id }})"
                    wire:confirm="Restore this system template to its default content?"
                    class="s-btn s-btn-ghost s-btn-sm">
                Restore default
            </button>
        @elseif(! $item->is_system)
            <button type="button"
                    wire:click="$parent.deleteTemplate({{ $item->id }})"
                    wire:confirm="Delete this template?"
                    class="s-btn-ghost s-btn-xs text-[var(--red)]">
                Delete
            </button>
        @endif
    @endcan
</div>
