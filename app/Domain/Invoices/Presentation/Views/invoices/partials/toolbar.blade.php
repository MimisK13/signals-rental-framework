<x-signals.column-toggle />
<x-signals.export-button />
@action('invoices.create')
    <a href="{{ route('invoices.create') }}" wire:navigate.hover class="s-btn s-btn-sm s-btn-accent">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4"><path d="M12 5v14M5 12h14"/></svg>
        New Draft
    </a>
@endaction
