<div class="flex w-full items-center gap-2">
    <a href="{{ route('availability.bookings', ['product' => $productId, 'from' => $fromDate, 'days' => $daysPeriod, 'store' => $storeId]) }}"
       wire:navigate
       class="min-w-0 flex-1 truncate font-medium text-[var(--blue)] hover:underline">
        {{ $productName }}
    </a>
    <a href="{{ route('products.show', $productId) }}"
       target="_blank"
       rel="noopener noreferrer"
       class="ml-auto shrink-0 text-[var(--text-muted)] hover:text-[var(--blue)]"
       title="{{ __('Product details') }}">
        <flux:icon.information-circle class="!size-3" />
    </a>
</div>
