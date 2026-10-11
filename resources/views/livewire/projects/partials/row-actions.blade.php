@if($item->trashed())
    <span class="text-[var(--text-muted)]">—</span>
@else
    <a
        href="{{ route('projects.show', $item) }}"
        wire:navigate.hover
        role="menuitem"
        class="s-dropdown-item"
    >
        <x-dense::icons.eye size="4" aria-hidden="true" />
        View
    </a>
    <a
        href="{{ route('projects.edit', $item) }}"
        wire:navigate.hover
        role="menuitem"
        class="s-dropdown-item"
    >
        <x-dense::icons.pencil size="4" aria-hidden="true" />
        Edit
    </a>
    <div style="height: 1px; background: var(--card-border); margin: 4px 0;"></div>
    <button
        type="button"
        role="menuitem"
        x-on:click="open = false"
        wire:click="$parent.archive({{ $item->id }})"
        wire:confirm="Archive {{ $item->name }}?"
        class="s-dropdown-item"
    >
        <x-dense::icons.trash size="4" aria-hidden="true" />
        Archive
    </button>
@endif
