<a href="{{ route('invoices.show', $item->invoice_id) }}" wire:navigate class="font-medium text-[var(--link)] hover:underline">
    {{ $item->number }}
</a>
