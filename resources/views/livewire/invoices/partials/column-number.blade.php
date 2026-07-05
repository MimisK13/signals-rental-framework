<a href="{{ route('invoices.show', $item) }}" wire:navigate class="font-medium text-[var(--link)] hover:underline">
    {{ $item->number }}
</a>
