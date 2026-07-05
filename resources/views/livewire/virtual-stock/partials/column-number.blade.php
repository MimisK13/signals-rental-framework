<a href="{{ route('virtual-stock.show', $item) }}" wire:navigate class="font-medium text-[var(--link)] hover:underline">
    {{ $item->number }}
</a>
@if($item->isLateReturn())
    <span class="s-badge s-badge-amber ml-1">Late</span>
@endif
