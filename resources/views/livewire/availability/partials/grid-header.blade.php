{{--
    Shared availability grid header row (master layout).

    @var list<string> $dayList
    @var string $labelColumn
    @var string $keyPrefix  wire:key prefix for day headers
    @var bool $showPeriodNav  Show prev/next period chevrons in the header row
--}}
@php
    $labelColumn = $labelColumn ?? __('Product');
    $keyPrefix = $keyPrefix ?? 'head';
    $showPeriodNav = $showPeriodNav ?? true;
@endphp

<thead>
    <tr>
        <th class="s-avail-group-product-col s-avail-group-product-head text-left">
            @if($showPeriodNav)
                <button type="button"
                        wire:click="previousPeriod"
                        class="s-avail-grid-period-nav s-avail-grid-period-nav--prev"
                        aria-label="{{ __('Previous period') }}">
                    <flux:icon.chevron-double-left class="!size-3.5" />
                </button>
            @endif
            @if($labelColumn !== '')
                <span>{{ $labelColumn }}</span>
            @endif
        </th>
        @foreach($dayList as $day)
            @php $carbonDay = \Illuminate\Support\Carbon::parse($day); @endphp
            <th wire:key="{{ $keyPrefix }}-{{ $day }}"
                class="s-avail-group-day-col s-avail-group-head {{ $carbonDay->isWeekend() ? 's-avail-group-weekend' : '' }}">
                <div class="s-avail-group-day">{{ $carbonDay->format('D') }}</div>
                <div class="s-avail-group-date">{{ $carbonDay->format('j M') }}</div>
            </th>
        @endforeach
        @if($showPeriodNav)
            <th class="s-avail-group-nav-col s-avail-group-nav-head">
                <button type="button"
                        wire:click="nextPeriod"
                        class="s-avail-grid-period-nav"
                        aria-label="{{ __('Next period') }}">
                    <flux:icon.chevron-double-right class="!size-3" />
                </button>
            </th>
        @endif
    </tr>
</thead>
