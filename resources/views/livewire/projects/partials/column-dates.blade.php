<span class="whitespace-nowrap">
    {{ $item->starts_at?->format('d M Y') ?? '—' }}{{ $item->ends_at ? ' – '.$item->ends_at->format('d M Y') : '' }}
</span>
