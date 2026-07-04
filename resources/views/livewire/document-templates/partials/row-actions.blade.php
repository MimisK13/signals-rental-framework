@can('update', $item)
    {{-- Plain load: the editor uses a different layout; cross-layout wire:navigate breaks CodeMirror mounting. --}}
    <a href="{{ route('admin.document-templates.edit', $item) }}" class="s-dropdown-item" style="text-decoration: none;">
        <flux:icon.pencil-square class="!size-3.5 shrink-0" />
        Edit
    </a>
    <button
        type="button"
        x-on:click="open = false; $wire.$parent.duplicateTemplate({{ $item->id }})"
        class="s-dropdown-item"
        style="width: 100%;"
    >
        <flux:icon.document-duplicate class="!size-3.5 shrink-0" />
        Duplicate
    </button>
    @if(! $item->is_default)
        <button
            type="button"
            x-on:click="open = false; $wire.$parent.setDefault({{ $item->id }})"
            class="s-dropdown-item"
            style="width: 100%;"
        >
            <flux:icon.star class="!size-3.5 shrink-0" />
            Set default
        </button>
    @endif
    @if($item->is_system)
        <button
            type="button"
            x-on:click="open = false; $wire.$parent.restoreDefault({{ $item->id }})"
            wire:confirm="Restore this system template to its default content?"
            class="s-dropdown-item"
            style="width: 100%;"
        >
            <flux:icon.arrow-path class="!size-3.5 shrink-0" />
            Restore default
        </button>
    @elseif(! $item->is_system)
        <div style="height: 1px; background: var(--card-border); margin: 4px 0;"></div>
        <button
            type="button"
            x-on:click="open = false; $wire.$parent.deleteTemplate({{ $item->id }})"
            wire:confirm="Delete this template?"
            class="s-dropdown-item"
            style="color: var(--red); width: 100%;"
        >
            <flux:icon.trash class="!size-3.5 shrink-0" />
            Delete
        </button>
    @endif
@endcan
