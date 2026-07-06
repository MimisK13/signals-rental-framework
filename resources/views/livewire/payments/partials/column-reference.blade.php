@if($item->reference)
    <a href="{{ route('invoices.show', $item->invoice_id) }}" wire:navigate class="font-medium text-[var(--link)] hover:underline">
        {{ $item->reference }}
    </a>
@else
    <a href="{{ route('invoices.show', $item->invoice_id) }}" wire:navigate class="text-[var(--text-muted)] hover:underline">—</a>
@endif
