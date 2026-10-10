<a href="{{ route('invoices.show', $item) }}" wire:navigate.hover class="font-medium text-[var(--link)] hover:underline">
    {{ $item->number }}
</a>
