{{--
    Shared day cell for availability grids (master cell renderer).

    Modes:
    - availability: group grid cell (available / held + used-pct fill)
    - demand: solid full-height bar (opportunities / assets)
    - summary: plain centred number
    - empty: middot placeholder
--}}
@php
    $mode = $mode ?? 'availability';
    $carbonDay = \Illuminate\Support\Carbon::parse($date);
    $weekendClass = $carbonDay->isWeekend() ? 's-avail-group-weekend' : '';
    $monthStartClass = $carbonDay->day === 1 ? 's-planning-month-start' : '';
    $tdClass = trim('s-avail-group-td '.$weekendClass.' '.$monthStartClass.' '.($tdClass ?? ''));
@endphp

<td wire:key="{{ $wireKey }}" class="{{ $tdClass }}">
    @if($mode === 'availability')
        @php
            $cellInner = ! ($cell['present'] ?? true)
                ? '<span class="block py-1 text-center text-[9px] text-[var(--text-muted)]">&middot;</span>'
                : view('livewire.availability.partials.grid-cell-availability', ['cell' => $cell])->render();
        @endphp
        @if(! empty($bookingsUrl))
            <a href="{{ $bookingsUrl }}" wire:navigate.hover class="s-avail-group-cell-link" title="{{ __('View product bookings') }}">
                {!! $cellInner !!}
            </a>
        @else
            {!! $cellInner !!}
        @endif
    @elseif($mode === 'demand')
        @if(($cell['tone'] ?? null) !== null)
            <div @class([
                's-avail-group-cell s-avail-group-cell--solid',
                's-avail-group-cell--buffer' => ($cell['tone'] ?? null) === 'buffer',
            ]) style="--demand-colour: {{ $cell['colour'] }};">
                @if(($cell['quantity'] ?? null) !== null)
                    <span class="s-avail-group-cell-available">{{ $cell['quantity'] }}</span>
                @endif
            </div>
        @endif
    @elseif($mode === 'summary')
        <div class="s-avail-grid-summary-wrap">
            <span class="s-avail-grid-summary">{{ $cell['value'] ?? '' }}</span>
        </div>
    @endif
</td>
