<a href="{{ route('opportunities.assets', $item) }}" wire:navigate.hover class="font-medium text-[var(--text)] hover:text-[var(--blue)]">
    {{ $item->number ?: '#'.$item->id }}
</a>
@if ($item->reference)
    <div class="text-xs text-[var(--text-muted)]">{{ $item->reference }}</div>
@endif
<div class="text-xs text-[var(--text-muted)]">{{ $item->subject }}</div>
