<a href="{{ route('invoices.show', $item->invoice_id) }}" wire:navigate.hover class="font-medium text-[var(--link)] hover:underline">
    {{ $item->number }}
</a>
