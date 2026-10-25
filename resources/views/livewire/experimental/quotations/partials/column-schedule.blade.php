<span class="s-cell-mono">
    {{ $item->starts_at?->format('d M Y') ?? '—' }}
    @if($item->ends_at)
        <span class="text-[var(--text-muted)]">→ {{ $item->ends_at->format('d M Y') }}</span>
    @endif
</span>
