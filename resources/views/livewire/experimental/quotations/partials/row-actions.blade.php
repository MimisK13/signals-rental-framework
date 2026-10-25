<a href="{{ route('experimental.quotations.edit', $item) }}" wire:navigate.hover class="s-dropdown-item">
    <flux:icon.pencil-square class="size-3.5" />
    Edit
</a>
<a href="{{ route('opportunities.show', $item) }}" wire:navigate.hover class="s-dropdown-item">
    <flux:icon.eye class="size-3.5" />
    Open canonical view
</a>
