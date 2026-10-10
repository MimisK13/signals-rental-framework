@if($item->invoice)
    <a href="{{ route('invoices.show', $item->invoice) }}" wire:navigate.hover class="text-[var(--link)] hover:underline">
        {{ $item->invoice->number }}
    </a>
@else
    —
@endif
