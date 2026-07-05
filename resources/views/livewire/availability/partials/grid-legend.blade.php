{{-- Shared availability grid key / legend (RMS-style). --}}
<div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1.5 text-[11px] text-[var(--text-muted)]">
    <span class="font-semibold uppercase tracking-wide">{{ __('Key') }}</span>
    <span class="flex items-center gap-1.5">
        <span class="s-avail-group-legend s-avail-group-cell--available"></span>
        {{ __('Available') }}
    </span>
    <span class="flex items-center gap-1.5">
        <span class="s-avail-group-legend s-avail-group-cell--warning"></span>
        {{ __('Warning') }}
    </span>
    <span class="flex items-center gap-1.5">
        <span class="s-avail-group-legend s-avail-group-cell--shortage"></span>
        {{ __('Shortage') }}
    </span>
    <span class="flex items-center gap-1.5">
        <span class="s-avail-group-legend s-avail-group-cell--booked"></span>
        {{ __('Booked') }}
    </span>
    <span><span class="font-semibold text-[var(--text-secondary)]">x</span> = {{ __('quantity available') }}</span>
    <span><span class="font-semibold text-[var(--text-secondary)]">(x)</span> = {{ __('quantity held') }}</span>
    <span><span class="font-semibold text-[var(--text-secondary)]">[x]</span> = {{ __('quantity on sub-rent') }}</span>
</div>
