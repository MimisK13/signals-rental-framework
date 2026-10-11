<a href="{{ route('opportunities.assets', $item) }}" wire:navigate.hover role="menuitem" class="s-dropdown-item">
    <x-dense::icons.eye size="4" aria-hidden="true" />
    Open execution
</a>
<a href="{{ route('opportunities.show', $item) }}" wire:navigate.hover role="menuitem" class="s-dropdown-item">
    <x-dense::icons.eye size="4" aria-hidden="true" />
    View
</a>
