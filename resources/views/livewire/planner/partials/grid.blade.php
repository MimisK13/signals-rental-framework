{{--
    Planner overlap grid: lane-packed job cards (headers live in grid-header partial).
--}}
@php
    /** @var list<array<string, mixed>> $rows */
    $rows = $this->rows;
    /** @var list<\Illuminate\Support\Carbon> $days */
    $days = $this->dayColumns();
    $dayCount = max(1, count($days));
    $colWidth = 100 / $dayCount;
    $phaseColours = $this->phaseColours();
    $customerBadgeColours = $this->customerBadgeColours();
    $statusRowHeight = 16;
    $lineBodyBottomPad = 4;
    $lineHeight = 13;
    $lineBodyHeight = 4 + ($lineHeight * 4) + $lineBodyBottomPad;
    $laneGap = $this->cardGapPx();
    $laneHeight = $lineBodyHeight + $statusRowHeight;
    $laneStride = $laneHeight + $laneGap;
    $today = \Illuminate\Support\Carbon::today('UTC')->toDateString();
@endphp

@if(count($rows) === 0)
    <x-signals.empty
        title="{{ __('No jobs in this window') }}"
        description="{{ __('No quotes or orders fall within the selected dates and filters.') }}">
        <x-slot:icon><flux:icon.calendar-days class="!size-7" /></x-slot:icon>
    </x-signals.empty>
@else
    @php
        $maxLanes = 1;
        foreach ($rows as $r) {
            $maxLanes = max($maxLanes, (int) ($r['lanes'] ?? 1));
        }
        $minTrackHeight = $maxLanes > 0
            ? ($maxLanes * $laneStride - $laneGap)
            : 0;
    @endphp
    <div class="s-panel relative w-full !p-0 overflow-hidden border-t-0 !rounded-t-none">
        <div class="relative z-0 w-full" style="min-height: {{ $minTrackHeight }}px;">
            @foreach($days as $i => $day)
                @php
                    $dateKey = $day->toDateString();
                    $isToday = $dateKey === $today;
                    $isWeekend = $day->isWeekend();
                    $isMonthStart = $day->day === 1;
                @endphp
                <div class="pointer-events-none absolute top-0 bottom-0 border-r border-[var(--card-border)] {{ $isWeekend ? 'bg-black/[0.04] dark:bg-white/[0.04]' : '' }} {{ $isToday ? 'bg-[#fff3cd]/25 ring-1 ring-inset ring-[#ffc107]/40' : '' }} {{ $isMonthStart ? 's-planning-month-start' : '' }}"
                     style="left: {{ $i * $colWidth }}%; width: {{ $colWidth }}%;"
                     wire:key="grid-{{ $dateKey }}"></div>
            @endforeach

            @foreach($rows as $row)
                @php
                    $lane = (int) ($row['lane'] ?? 0);
                    $top = $lane * $laneStride;
                @endphp
                @include('livewire.planner.partials.bar', [
                    'row' => $row,
                    'top' => $top,
                    'height' => $laneHeight,
                    'colWidth' => $colWidth,
                    'statusRowHeight' => $statusRowHeight,
                    'lineBodyBottomPad' => $lineBodyBottomPad,
                    'phaseColours' => $phaseColours,
                    'customerBadgeColours' => $customerBadgeColours,
                ])
            @endforeach
        </div>
    </div>
@endif
