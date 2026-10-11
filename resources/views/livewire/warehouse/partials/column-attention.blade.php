@if ($item->has_shortage)
    <span class="s-badge s-badge-red">Shortage</span>
@else
    <span class="text-xs text-[var(--text-muted)]">—</span>
@endif
