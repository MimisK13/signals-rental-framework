{{--
    Section divider row inside an availability grid (e.g. Opportunities, Assets).

    Label sits in the sticky left column, aligned with row labels below.
--}}
<tr wire:key="{{ $wireKey }}">
    <td class="sticky left-0 z-10 bg-[var(--card-bg)] s-avail-group-product-col s-avail-grid-section text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">
        {{ $label }}
    </td>
    <td colspan="{{ $dataColspan }}" class="s-avail-grid-section bg-[var(--card-bg)]"></td>
    @include('livewire.availability.partials.grid-nav-spacer')
</tr>
